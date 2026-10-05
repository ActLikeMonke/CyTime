<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    /**
     * @return array{year: int, entitlement: int, carry_over: int, used: int, remaining: int, pending: int}
     */
    public function for(User $user, int $year): array
    {
        $entitlement = $user->annual_leave_days;
        $carryOver = $this->carryOverForYear($user, $year, $entitlement);
        $used = $this->daysForRequests($user, $year, Absence::STATUS_APPROVED);
        $pending = $this->daysForRequests($user, $year, Absence::STATUS_PENDING);
        $available = max(0, $entitlement + $carryOver - $used);

        return [
            'year' => $year,
            'entitlement' => $entitlement,
            'carry_over' => $carryOver,
            'used' => $used,
            'remaining' => $available,
            'pending' => $pending,
        ];
    }

    public function daysForRequest(User $user, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $schedules = $user->workSchedules()
            ->whereDate('valid_from', '<=', $end->toDateString())
            ->orderByDesc('valid_from')
            ->get();
        $holidays = DB::table('holidays')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->all();
        $days = 0;

        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            if ($this->isWorkday($schedules, $date)
                && ! in_array($date->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        return $days;
    }

    private function daysForRequests(User $user, int $year, string $status): int
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = $start->endOfYear();
        $absences = $user->absences()
            ->where('type', Absence::TYPE_VACATION)
            ->where('status', $status)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();
        $days = [];
        $schedules = $user->workSchedules()
            ->whereDate('valid_from', '<=', $end->toDateString())
            ->orderByDesc('valid_from')
            ->get();
        $holidays = DB::table('holidays')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->all();

        foreach ($absences as $absence) {
            $absenceStart = CarbonImmutable::parse($absence->start_date)->max($start);
            $absenceEnd = CarbonImmutable::parse($absence->end_date)->min($end);
            for ($date = $absenceStart; $date <= $absenceEnd; $date = $date->addDay()) {
                $dateKey = $date->toDateString();
                if ($this->isWorkday($schedules, $date) && ! in_array($dateKey, $holidays, true)) {
                    $days[$dateKey] = true;
                }
            }
        }

        return count($days);
    }

    private function carryOverForYear(User $user, int $year, int $entitlement): int
    {
        $firstSchedule = $user->workSchedules()->orderBy('valid_from')->first();
        if (! $firstSchedule instanceof WorkSchedule) {
            return 0;
        }

        $firstYear = (int) $firstSchedule->valid_from->format('Y');
        $carryOver = 0;
        for ($leaveYear = $firstYear; $leaveYear < $year; $leaveYear++) {
            $used = $this->daysForRequests($user, $leaveYear, Absence::STATUS_APPROVED);
            $carryOver = max(0, $entitlement - max(0, $used - $carryOver));
        }

        return $carryOver;
    }

    /**
     * @param  Collection<int, WorkSchedule>  $schedules
     */
    private function isWorkday(Collection $schedules, CarbonImmutable $date): bool
    {
        $schedule = $schedules->first(
            fn (WorkSchedule $candidate): bool => $candidate->valid_from->toDateString() <= $date->toDateString(),
        );

        return $schedule instanceof WorkSchedule
            && in_array(strtolower($date->format('D')), $schedule->work_days, true);
    }
}
