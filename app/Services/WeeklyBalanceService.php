<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeeklyBalanceService
{
    /**
     * @return array{week_start: string, week_end: string, schedule: WorkSchedule|null, target_minutes: int, worked_minutes: int, remaining_minutes: int, balance_minutes: int, workdays: array<int, string>}
     */
    public function for(User $user, CarbonImmutable $date): array
    {
        $weekStart = $date->startOfWeek();
        $weekEnd = $weekStart->endOfWeek();
        $schedules = $this->schedulesThrough($user, $weekEnd);
        $schedule = $this->scheduleForDate($schedules, $date);
        $workDays = $schedule instanceof WorkSchedule ? $schedule->work_days : [];
        $holidays = $this->holidaysBetween($weekStart, $weekEnd);
        $absences = $this->applicableAbsences($user, $weekStart, $weekEnd);

        $targetMinutes = 0.0;
        $elapsedTargetMinutes = 0.0;
        for ($day = $weekStart; $day <= $weekEnd; $day = $day->addDay()) {
            $daySchedule = $this->scheduleForDate($schedules, $day);
            if (! $daySchedule instanceof WorkSchedule || ! $this->isWorkday($daySchedule, $day)) {
                continue;
            }

            $dateKey = $day->toDateString();
            $hasApplicableAbsence = $this->hasApplicableAbsence($absences, $dateKey);

            if (! in_array($dateKey, $holidays, true) && ! $hasApplicableAbsence) {
                $dailyTarget = $this->dailyTarget($daySchedule);
                $targetMinutes += $dailyTarget;

                if ($day < $date->startOfDay()) {
                    $elapsedTargetMinutes += $dailyTarget;
                }
            }
        }
        $targetMinutes = (int) round($targetMinutes);
        $elapsedTargetMinutes = (int) round($elapsedTargetMinutes);

        $workedMinutes = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekEnd->toDateString())
            ->where('status', TimeEntry::STATUS_FINAL)
            ->whereNotNull('end_time')
            ->get()
            ->sum(fn (TimeEntry $entry): int => $entry->netMinutes());

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'schedule' => $schedule,
            'target_minutes' => $targetMinutes,
            'worked_minutes' => $workedMinutes,
            'remaining_minutes' => max($elapsedTargetMinutes - $workedMinutes, 0),
            'balance_minutes' => $workedMinutes - $targetMinutes,
            'workdays' => $workDays,
        ];
    }

    public function cumulativeBalance(User $user, CarbonImmutable $throughDate): int
    {
        $firstSchedule = $user->workSchedules()->orderBy('valid_from')->first();
        if (! $firstSchedule instanceof WorkSchedule) {
            return 0;
        }

        $start = CarbonImmutable::parse($firstSchedule->valid_from)->startOfWeek();
        $end = $throughDate->endOfWeek();
        $schedules = $this->schedulesThrough($user, $end);
        $holidays = $this->holidaysBetween($start, $end);
        $absences = $this->applicableAbsences($user, $start, $end);
        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('status', TimeEntry::STATUS_FINAL)
            ->whereNotNull('end_time')
            ->get();

        $workedMinutesByWeek = [];
        foreach ($entries as $entry) {
            $weekKey = $entry->date->startOfWeek()->toDateString();
            $workedMinutesByWeek[$weekKey] = ($workedMinutesByWeek[$weekKey] ?? 0) + $entry->netMinutes();
        }

        $targetMinutesByWeek = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $daySchedule = $this->scheduleForDate($schedules, $day);
            if (! $daySchedule instanceof WorkSchedule || ! $this->isWorkday($daySchedule, $day)) {
                continue;
            }

            $dateKey = $day->toDateString();
            if (in_array($dateKey, $holidays, true) || $this->hasApplicableAbsence($absences, $dateKey)) {
                continue;
            }

            $weekKey = $day->startOfWeek()->toDateString();
            $targetMinutesByWeek[$weekKey] = ($targetMinutesByWeek[$weekKey] ?? 0)
                + $this->dailyTarget($daySchedule);
        }

        $balanceMinutes = 0;
        $weekKeys = array_unique([...array_keys($targetMinutesByWeek), ...array_keys($workedMinutesByWeek)]);
        foreach ($weekKeys as $weekKey) {
            $balanceMinutes += ($workedMinutesByWeek[$weekKey] ?? 0)
                - (int) round($targetMinutesByWeek[$weekKey] ?? 0);
        }

        return $balanceMinutes;
    }

    /**
     * @return EloquentCollection<int, WorkSchedule>
     */
    private function schedulesThrough(User $user, CarbonImmutable $date): EloquentCollection
    {
        return $user->workSchedules()
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->orderByDesc('valid_from')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, WorkSchedule>  $schedules
     */
    private function scheduleForDate(EloquentCollection $schedules, CarbonImmutable $date): ?WorkSchedule
    {
        $schedule = $schedules->first(
            fn (WorkSchedule $candidate): bool => $candidate->valid_from->toDateString() <= $date->toDateString(),
        );

        return $schedule instanceof WorkSchedule ? $schedule : null;
    }

    /**
     * @return array<int, string>
     */
    private function holidaysBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('holidays')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')
            ->map(fn ($holiday): string => CarbonImmutable::parse($holiday)->toDateString())
            ->all();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function applicableAbsences(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return DB::table('absences')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('type', 'sick')
                        ->whereIn('status', ['pending', 'approved']);
                })->orWhere(function ($query): void {
                    $query->where('type', 'vacation')
                        ->where('status', 'approved');
                });
            })
            ->get(['type', 'status', 'start_date', 'end_date']);
    }

    /**
     * @param  Collection<int, \stdClass>  $absences
     */
    private function hasApplicableAbsence(Collection $absences, string $date): bool
    {
        return $absences->contains(
            fn (\stdClass $absence): bool => CarbonImmutable::parse($absence->start_date)->toDateString() <= $date
                && CarbonImmutable::parse($absence->end_date)->toDateString() >= $date,
        );
    }

    private function isWorkday(WorkSchedule $schedule, CarbonImmutable $date): bool
    {
        return in_array(strtolower($date->format('D')), $schedule->work_days, true);
    }

    private function dailyTarget(WorkSchedule $schedule): float
    {
        return (float) $schedule->weekly_hours * 60 / count($schedule->work_days);
    }
}
