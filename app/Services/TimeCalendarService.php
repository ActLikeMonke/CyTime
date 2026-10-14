<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\CalendarRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TimeCalendarService
{
    public function __construct(
        private readonly WorkTimeRuleService $rules,
        private readonly NrwHolidayService $holidays,
    ) {}

    /**
     * @return array{month: CarbonImmutable, weeks: array<int, array<int, array<string, mixed>>>}
     */
    public function for(User $user, string $month): array
    {
        $validated = Validator::make(['month' => $month], [
            'month' => ['required', 'date_format:Y-m', ...CalendarRange::monthRules()],
        ])->validate();
        $monthDate = CarbonImmutable::createFromFormat('!Y-m', $validated['month']);
        $start = $monthDate->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
        $end = $monthDate->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        for ($year = $start->year; $year <= $end->year; $year++) {
            $this->holidays->ensureYearGenerated($year);
        }

        $entries = $user->timeEntries()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->date->toDateString());
        $absences = $user->absences()
            ->whereIn('status', [Absence::STATUS_PENDING, Absence::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();
        $holidays = DB::table('holidays')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('name', 'date')
            ->mapWithKeys(fn (string $name, string $date): array => [
                CarbonImmutable::parse($date)->toDateString() => $name,
            ]);
        $absenceDays = $this->absenceDays($absences, $start, $end);
        $weeks = [];

        for ($weekStart = $start; $weekStart <= $end; $weekStart = $weekStart->addWeek()) {
            $week = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $date = $weekStart->addDays($offset);
                $dateKey = $date->toDateString();
                $dayEntries = $entries->get($dateKey, collect());
                $entry = $dayEntries->first();
                $holiday = $holidays->get($dateKey);
                $absence = $absenceDays->get($dateKey);
                $warnings = [];

                $completedEntry = $dayEntries->first(fn (TimeEntry $candidate): bool => $candidate->end_time !== null);
                if ($completedEntry instanceof TimeEntry) {
                    $warnings = $this->rules->evaluate($user, [
                        'date' => $dateKey,
                        'start_time' => substr((string) $completedEntry->start_time, 0, 5),
                        'end_time' => substr((string) $completedEntry->end_time, 0, 5),
                        'break_minutes' => $completedEntry->break_minutes,
                        'is_school_day' => $completedEntry->is_school_day,
                    ], $completedEntry->id);
                }

                $status = match (true) {
                    $holiday !== null => 'holiday',
                    ($absence['type'] ?? null) === Absence::TYPE_SICK => 'sick',
                    ($absence['type'] ?? null) === Absence::TYPE_VACATION
                        && $absence['status'] === Absence::STATUS_PENDING => 'vacation_pending',
                    ($absence['type'] ?? null) === Absence::TYPE_VACATION => 'vacation',
                    $entry instanceof TimeEntry => 'booked',
                    default => 'empty',
                };

                $week[] = [
                    'date' => $dateKey,
                    'day' => (int) $date->format('j'),
                    'is_current_month' => $date->format('Y-m') === $monthDate->format('Y-m'),
                    'is_today' => $date->isToday(),
                    'status' => $status,
                    'details' => $this->details($status, $holiday, $absence, $dayEntries, $warnings),
                    'entries' => $dayEntries->map(fn (TimeEntry $candidate): array => [
                        'id' => $candidate->id,
                        'start' => substr((string) $candidate->start_time, 0, 5),
                        'end' => $candidate->end_time === null ? null : substr((string) $candidate->end_time, 0, 5),
                        'break_minutes' => $candidate->break_minutes,
                        'net_minutes' => $candidate->netMinutes(),
                        'status' => $candidate->status,
                    ])->all(),
                    'holiday_name' => $holiday,
                    'absence_type' => $absence['type'] ?? null,
                    'absence_status' => $absence['status'] ?? null,
                    'entry_id' => $entry?->id,
                    'entry_start' => $entry === null ? null : substr((string) $entry->start_time, 0, 5),
                    'entry_end' => $entry?->end_time === null ? null : substr((string) $entry->end_time, 0, 5),
                    'entry_break' => $entry?->break_minutes,
                    'entry_net_minutes' => $dayEntries->isEmpty() ? null : $dayEntries->sum(fn (TimeEntry $candidate): int => $candidate->netMinutes()),
                    'entry_status' => $entry?->status,
                    'warnings' => $warnings,
                ];
            }
            $weeks[] = $week;
        }

        return ['month' => $monthDate, 'weeks' => $weeks];
    }

    /**
     * @param  Collection<int, Absence>  $absences
     * @return Collection<string, array{type: string, status: string}>
     */
    private function absenceDays(Collection $absences, CarbonImmutable $gridStart, CarbonImmutable $gridEnd): Collection
    {
        $days = collect();

        foreach ($absences as $absence) {
            $start = CarbonImmutable::parse($absence->start_date)->max($gridStart);
            $end = CarbonImmutable::parse($absence->end_date)->min($gridEnd);
            for ($date = $start; $date <= $end; $date = $date->addDay()) {
                $key = $date->toDateString();
                $current = $days->get($key);
                if ($current === null
                    || ($absence->type === Absence::TYPE_SICK && $current['type'] !== Absence::TYPE_SICK)
                    || ($absence->type === Absence::TYPE_VACATION && $current['type'] === Absence::TYPE_VACATION
                        && $absence->status === Absence::STATUS_APPROVED)) {
                    $days->put($key, ['type' => $absence->type, 'status' => $absence->status]);
                }
            }
        }

        return $days;
    }

    /**
     * @param  array{type: string, status: string}|null  $absence
     * @param  Collection<int, TimeEntry>  $entries
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function details(
        string $status,
        ?string $holiday,
        ?array $absence,
        Collection $entries,
        array $warnings,
    ): string {
        $detail = match ($status) {
            'holiday' => __('Holiday – :name', ['name' => $holiday]),
            'sick' => __('Sick day – :status', ['status' => __($absence['status'])]),
            'vacation_pending' => __('Vacation – pending approval'),
            'vacation' => __('Vacation – approved'),
            'booked' => $entries->map(fn (TimeEntry $entry): string => $this->entryDetails($entry))->implode(' · '),
            default => __('No entry'),
        };

        if ($status === 'booked' && $entries->count() > 1) {
            $detail .= ' · '.__('Total net: :hours h', [
                'hours' => number_format($entries->sum(fn (TimeEntry $entry): int => $entry->netMinutes()) / 60, 2),
            ]);
        }

        if ($warnings !== []) {
            $labels = collect($warnings)
                ->map(fn (array $warning): string => $warning['rule'].' — '.$warning['message'])
                ->implode('; ');
            $detail .= ' · '.__('Warning: :warnings', ['warnings' => $labels]);
        }

        return $detail;
    }

    private function entryDetails(TimeEntry $entry): string
    {
        return $entry->end_time === null
            ? __('Clocked in at :start', ['start' => substr((string) $entry->start_time, 0, 5)])
            : __('Work :start–:end, break :break min, net :net h', [
                'start' => substr((string) $entry->start_time, 0, 5),
                'end' => substr((string) $entry->end_time, 0, 5),
                'break' => $entry->break_minutes,
                'net' => number_format($entry->netMinutes() / 60, 2),
            ]);
    }
}
