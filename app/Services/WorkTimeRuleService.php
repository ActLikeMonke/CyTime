<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class WorkTimeRuleService
{
    /**
     * @param  array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}  $entry
     * @return array<int, array{code: string, rule: string, message: string}>
     */
    public function evaluate(User $user, array $entry, ?int $excludeEntryId = null): array
    {
        return $this->evaluatePeriods($user, [$entry], $excludeEntryId);
    }

    /**
     * @param  array<int, array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}>  $periods
     * @return array<int, array{code: string, rule: string, message: string}>
     */
    public function evaluatePeriods(User $user, array $periods, ?int $excludeEntryId = null): array
    {
        $date = CarbonImmutable::parse($periods[0]['date']);
        $existing = $user->timeEntries()
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('end_time')
            ->when($excludeEntryId !== null, fn ($query) => $query->whereKeyNot($excludeEntryId))
            ->get()
            ->map(fn (TimeEntry $entry): array => [
                'date' => $entry->date->toDateString(),
                'start_time' => (string) $entry->start_time,
                'end_time' => (string) $entry->end_time,
                'break_minutes' => $entry->break_minutes,
                'is_school_day' => $entry->is_school_day,
            ]);
        $dayPeriods = $existing->concat($periods)->sortBy('start_time')->values();
        $start = $this->at($date, $dayPeriods->first()['start_time']);
        $end = $this->at($date, $dayPeriods->max('end_time'));
        $workMinutes = 0;
        $continuousMinutes = 0;
        $maximumContinuousMinutes = 0;
        $previousEnd = null;
        foreach ($dayPeriods as $period) {
            $periodStart = $this->at($date, $period['start_time']);
            $periodEnd = $this->at($date, $period['end_time']);
            $net = max(0, (int) (($periodEnd->getTimestamp() - $periodStart->getTimestamp()) / 60) - $period['break_minutes']);
            $workMinutes += $net;
            if ($previousEnd !== null && $periodStart > $previousEnd) {
                $continuousMinutes = 0;
            }
            if ($period['break_minutes'] > 0) {
                $continuousMinutes = 0;
            } else {
                $continuousMinutes += $net;
            }
            $maximumContinuousMinutes = max($maximumContinuousMinutes, $continuousMinutes);
            $previousEnd = $periodEnd;
        }
        $breakMinutes = max(0, (int) (($end->getTimestamp() - $start->getTimestamp()) / 60) - $workMinutes);
        $isSchoolDay = $dayPeriods->contains(fn (array $period): bool => $period['is_school_day']);
        $isMinor = $user->birth_date !== null
            && CarbonImmutable::parse($user->birth_date)
                ->addYears((int) config('worktime.minor.adult_age', 18))
                ->gt($date);
        $warnings = [];
        $this->evaluateBreaks($warnings, $workMinutes, $breakMinutes, $isMinor);
        if ($isMinor && $breakMinutes > 0
            && $maximumContinuousMinutes > (int) config('worktime.minor.maximum_continuous_minutes', 270)) {
            $limit = (int) config('worktime.minor.maximum_continuous_minutes', 270);
            $this->warn($warnings, 'minor-continuous-work', 'JArbSchG §11',
                "Work exceeds the configured maximum of {$limit} minutes without a break.");
        }
        $this->evaluateDayLimit($warnings, $workMinutes, $isSchoolDay, $isMinor);
        foreach ($dayPeriods as $period) {
            $this->evaluateCalendarDay($warnings, $date,
                $this->at($date, $period['start_time']), $this->at($date, $period['end_time']), $isMinor);
        }
        $this->evaluateRest($warnings, $user, $date, $start, $end, $isMinor, $excludeEntryId);

        if ($isMinor) {
            $this->evaluateMinorWeek($warnings, $user, [
                ...$periods[0],
                'is_school_day' => $isSchoolDay,
            ], $workMinutes, $excludeEntryId);
        }

        return collect($warnings)->unique(fn (array $warning): string => $warning['code'].'|'.$warning['message'])->values()->all();
    }

    /**
     * @return array<int, array{entry_id: int, date: string, warnings: array<int, array{code: string, rule: string, message: string}>}>
     */
    public function recentViolations(User $user, ?int $days = null): array
    {
        $days ??= (int) config('worktime.supervisor_recent_violation_days', 7);
        $from = today()->subDays(max(0, $days - 1));
        $entries = $user->timeEntries()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', today()->toDateString())
            ->where('status', TimeEntry::STATUS_FINAL)
            ->whereNotNull('end_time')
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        return $entries
            ->map(function (TimeEntry $entry): ?array {
                $warnings = $this->evaluate($entry->user, [
                    'date' => $entry->date->toDateString(),
                    'start_time' => (string) $entry->start_time,
                    'end_time' => (string) $entry->end_time,
                    'break_minutes' => $entry->break_minutes,
                    'is_school_day' => $entry->is_school_day,
                ], $entry->id);

                return $warnings === [] ? null : [
                    'entry_id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'warnings' => $warnings,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function evaluateBreaks(array &$warnings, int $workMinutes, int $breakMinutes, bool $isMinor): void
    {
        if ($isMinor) {
            $threshold = (int) config('worktime.minor.break_after_minutes', 270);
            $longThreshold = (int) config('worktime.minor.long_break_after_minutes', 360);
            $required = (int) config('worktime.minor.break_minutes_required', 30);
            $longRequired = (int) config('worktime.minor.long_break_minutes_required', 60);
            $continuousLimit = (int) config('worktime.minor.maximum_continuous_minutes', 270);

            if ($workMinutes > $continuousLimit && $breakMinutes === 0) {
                $this->warn($warnings, 'minor-continuous-work', 'JArbSchG §11',
                    "Work exceeds the configured maximum of {$continuousLimit} minutes without a break.");
            }

            if ($workMinutes > $longThreshold && $breakMinutes < $longRequired) {
                $this->warn($warnings, 'minor-long-break', 'JArbSchG §11',
                    "More than {$longThreshold} minutes of work requires a {$longRequired}-minute break.");
            } elseif ($workMinutes >= $threshold && $breakMinutes < $required) {
                $this->warn($warnings, 'minor-break', 'JArbSchG §11',
                    "Work from {$threshold} minutes requires a {$required}-minute break.");
            }

            return;
        }

        $breakAfter = (int) config('worktime.adult.break_after_minutes', 360);
        $required = (int) config('worktime.adult.break_after_minutes_required', 30);
        $longAfter = (int) config('worktime.adult.long_day_after_minutes', 540);
        $longRequired = (int) config('worktime.adult.long_day_break_minutes_required', 45);

        if ($workMinutes > $longAfter && $breakMinutes < $longRequired) {
            $this->warn($warnings, 'adult-long-break', 'ArbZG §4',
                "More than {$longAfter} minutes of work requires a {$longRequired}-minute break.");
        } elseif ($workMinutes > $breakAfter && $breakMinutes < $required) {
            $this->warn($warnings, 'adult-break', 'ArbZG §4',
                "More than {$breakAfter} minutes of work requires a {$required}-minute break.");
        }
    }

    /**
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function evaluateDayLimit(array &$warnings, int $workMinutes, bool $isSchoolDay, bool $isMinor): void
    {
        $limit = $isMinor
            ? (int) config('worktime.minor.maximum_daily_minutes', 480)
            : (int) config('worktime.adult.daily_absolute_minutes', 600);

        if ($isMinor && $isSchoolDay) {
            $workMinutes += (int) config('worktime.minor.vocational_school_day_credit_minutes', 480);
        }

        if ($workMinutes > $limit) {
            $rule = $isMinor ? 'JArbSchG §8–9' : 'ArbZG §3';
            $this->warn($warnings, $isMinor ? 'minor-daily-limit' : 'adult-daily-limit', $rule,
                'The recorded and credited work exceeds the configured daily maximum of '.number_format($limit / 60, 2).' hours.');
        } elseif (! $isMinor && $workMinutes > (int) config('worktime.adult.daily_regular_minutes', 480)) {
            $this->warn($warnings, 'adult-extended-day', 'ArbZG §3',
                'The day exceeds 8 hours and uses the extended daily limit; the statutory averaging period still applies.');
        }
    }

    /**
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function evaluateCalendarDay(
        array &$warnings,
        CarbonImmutable $date,
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $isMinor,
    ): void {
        if ($isMinor) {
            $nightStart = (string) config('worktime.minor.night_work_start', '20:00');
            $nightEnd = (string) config('worktime.minor.night_work_end', '06:00');
            if ($start < $this->at($date, $nightEnd) || $end > $this->at($date, $nightStart)) {
                $this->warn($warnings, 'minor-night-work', 'JArbSchG §14',
                    "Work overlaps the configured restricted hours between {$nightStart} and {$nightEnd}.");
            }

            return;
        }

        if ($date->isSunday() && config('worktime.adult.warn_sunday_work', true)) {
            $this->warn($warnings, 'adult-sunday-work', 'ArbZG §9', 'Work on Sunday is generally prohibited.');
        }

        if (config('worktime.adult.warn_holiday_work', true) && DB::table('holidays')->whereDate('date', $date->toDateString())->exists()) {
            $this->warn($warnings, 'adult-holiday-work', 'ArbZG §9', 'Work on a public holiday is generally prohibited.');
        }
    }

    /**
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function evaluateRest(
        array &$warnings,
        User $user,
        CarbonImmutable $date,
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $isMinor,
        ?int $excludeEntryId,
    ): void {
        $required = (int) config(
            $isMinor ? 'worktime.minor.minimum_rest_minutes' : 'worktime.adult.minimum_rest_minutes',
            $isMinor ? 720 : 660,
        );
        $entries = $this->adjacentEntries($user, $date, $excludeEntryId);
        $previous = $entries->filter(
            fn (TimeEntry $candidate): bool => $candidate->date->toDateString() < $date->toDateString(),
        )->sortByDesc(fn (TimeEntry $candidate): string => $candidate->date->toDateString().' '.(string) $candidate->end_time)->first();
        $next = $entries->filter(
            fn (TimeEntry $candidate): bool => $candidate->date->toDateString() > $date->toDateString(),
        )->sortBy(fn (TimeEntry $candidate): string => $candidate->date->toDateString().' '.(string) $candidate->start_time)->first();
        $rule = $isMinor ? 'JArbSchG §13' : 'ArbZG §5';

        if ($previous instanceof TimeEntry && $previous->end_time !== null) {
            $previousEnd = $this->at(CarbonImmutable::parse($previous->date), (string) $previous->end_time);
            $restMinutes = (int) (($start->getTimestamp() - $previousEnd->getTimestamp()) / 60);
            if ($restMinutes < $required) {
                $this->warn($warnings, $isMinor ? 'minor-rest-period' : 'adult-rest-period', $rule,
                    "Rest before this workday is {$restMinutes} minutes; the configured minimum is {$required} minutes.");
            }
        }

        if ($next instanceof TimeEntry) {
            $nextStart = $this->at(CarbonImmutable::parse($next->date), (string) $next->start_time);
            $restMinutes = (int) (($nextStart->getTimestamp() - $end->getTimestamp()) / 60);
            if ($restMinutes < $required) {
                $this->warn($warnings, $isMinor ? 'minor-rest-period' : 'adult-rest-period', $rule,
                    "Rest before the next workday is {$restMinutes} minutes; the configured minimum is {$required} minutes.");
            }
        }
    }

    /**
     * @param  array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}  $entry
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function evaluateMinorWeek(array &$warnings, User $user, array $entry, int $workMinutes, ?int $excludeEntryId): void
    {
        $date = CarbonImmutable::parse($entry['date']);
        $weekStart = $date->startOfWeek();
        $weekEnd = $date->endOfWeek();
        $entries = $user->timeEntries()
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekEnd->toDateString())
            ->whereDate('date', '!=', $date->toDateString())
            ->whereNotNull('end_time')
            ->when($excludeEntryId !== null, fn ($query) => $query->whereKeyNot($excludeEntryId))
            ->get();
        $weeklyMinutes = $workMinutes;
        $workdays = [$date->toDateString() => true];
        $schoolDays = [];

        foreach ($entries as $existing) {
            $weeklyMinutes += $existing->netMinutes();
            $workdays[$existing->date->toDateString()] = true;
            if ($existing->is_school_day && ! isset($schoolDays[$existing->date->toDateString()])) {
                $weeklyMinutes += (int) config('worktime.minor.vocational_school_day_credit_minutes', 480);
                $schoolDays[$existing->date->toDateString()] = true;
            }
        }
        if ($entry['is_school_day']) {
            $weeklyMinutes += (int) config('worktime.minor.vocational_school_day_credit_minutes', 480);
        }

        $maxWeekly = (int) config('worktime.minor.maximum_weekly_minutes', 2400);
        if ($weeklyMinutes > $maxWeekly) {
            $this->warn($warnings, 'minor-weekly-limit', 'JArbSchG §8',
                'The week exceeds the configured maximum of '.number_format($maxWeekly / 60, 2).' hours, including credited vocational-school time.');
        }

        $maxDays = (int) config('worktime.minor.maximum_workdays_per_week', 5);
        if (count($workdays) > $maxDays) {
            $this->warn($warnings, 'minor-workdays-limit', 'JArbSchG §15',
                "The week includes more than the configured maximum of {$maxDays} workdays.");
        }
    }

    /**
     * @return EloquentCollection<int, TimeEntry>
     */
    private function adjacentEntries(User $user, CarbonImmutable $date, ?int $excludeEntryId): EloquentCollection
    {
        return $user->timeEntries()
            ->whereDate('date', '>=', $date->subDay()->toDateString())
            ->whereDate('date', '<=', $date->addDay()->toDateString())
            ->whereNotNull('end_time')
            ->when($excludeEntryId !== null, fn ($query) => $query->whereKeyNot($excludeEntryId))
            ->get();
    }

    private function at(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return $date->setTimeFromTimeString($time);
    }

    /**
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     */
    private function warn(array &$warnings, string $code, string $rule, string $message): void
    {
        $warnings[] = ['code' => $code, 'rule' => $rule, 'message' => $message];
    }
}
