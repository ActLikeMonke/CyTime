<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class SupervisorDashboardService
{
    public function __construct(
        private readonly LeaveBalanceService $leaveBalances,
        private readonly WeeklyBalanceService $weeklyBalances,
    ) {}

    /**
     * @return array<int, array{user: User, status: string, has_open_requests: bool, remaining_leave_days: int, used_leave_days: int, hour_balance_minutes: int}>
     */
    public function overview(string $filter, string $sort, string $direction): array
    {
        $validated = Validator::make(compact('filter', 'sort', 'direction'), [
            'filter' => ['required', 'in:all,open'],
            'sort' => ['required', 'in:name,remaining_leave_days,used_leave_days,status,hour_balance_minutes'],
            'direction' => ['required', 'in:asc,desc'],
        ])->validate();
        $employees = User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $absenceGroups = $this->dashboardAbsences($employees);
        $today = CarbonImmutable::today();
        $year = (int) $today->format('Y');
        $rows = [];

        foreach ($employees as $employee) {
            $absences = $absenceGroups->get($employee->id, collect());
            $pending = $absences->where('status', Absence::STATUS_PENDING);
            $status = $this->statusFor($absences, $today);
            $hasOpenRequests = $pending->isNotEmpty();

            if ($validated['filter'] === 'open' && ! $hasOpenRequests) {
                continue;
            }

            $leaveBalance = $this->leaveBalances->for($employee, $year);
            $rows[] = [
                'user' => $employee,
                'status' => $status,
                'has_open_requests' => $hasOpenRequests,
                'remaining_leave_days' => $leaveBalance['remaining'],
                'used_leave_days' => $leaveBalance['used'],
                'hour_balance_minutes' => $this->weeklyBalances->cumulativeBalance($employee, $today),
            ];
        }

        usort($rows, function (array $left, array $right) use ($validated): int {
            $sort = $validated['sort'];
            if ($sort === 'name') {
                $comparison = strcasecmp($left['user']->name, $right['user']->name);
            } elseif ($sort === 'status') {
                $comparison = strcmp($left['status'], $right['status']);
            } else {
                $comparison = $left[$sort] <=> $right[$sort];
            }

            if ($comparison === 0) {
                $comparison = strcasecmp($left['user']->name, $right['user']->name);
            }

            return $validated['direction'] === 'desc' ? -$comparison : $comparison;
        });

        return $rows;
    }

    /**
     * @return array{employee: User, month: CarbonImmutable, entries: EloquentCollection<int, TimeEntry>, absences: EloquentCollection<int, Absence>, total_minutes: int, weekly_balances: array<int, array{week_start: string, week_end: string, balance_minutes: int, worked_minutes: int, target_minutes: int}>}
     */
    public function employeeMonth(User $employee, string $month): array
    {
        $monthDate = $this->validatedMonth($month);
        $start = $monthDate->startOfMonth();
        $end = $monthDate->endOfMonth()->min(CarbonImmutable::today());
        $entries = $employee->timeEntries()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        $absences = $employee->absences()
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();
        $weeklyBalances = [];

        for ($week = $start->startOfWeek(); $week <= $end; $week = $week->addWeek()) {
            $balance = $this->weeklyBalances->for($employee, $week);
            $weeklyBalances[] = [
                'week_start' => $balance['week_start'],
                'week_end' => $balance['week_end'],
                'balance_minutes' => $balance['balance_minutes'],
                'worked_minutes' => $balance['worked_minutes'],
                'target_minutes' => $balance['target_minutes'],
            ];
        }

        return [
            'employee' => $employee,
            'month' => $monthDate,
            'entries' => $entries,
            'absences' => $absences,
            'total_minutes' => $entries
                ->where('status', TimeEntry::STATUS_FINAL)
                ->sum(fn (TimeEntry $entry): int => $entry->netMinutes()),
            'weekly_balances' => $weeklyBalances,
        ];
    }

    /**
     * @return array<int, array{employee_id: int, employee_name: string, month: string, worked_hours: string}>
     */
    public function monthlyHoursRows(string $month): array
    {
        $monthDate = $this->validatedMonth($month);
        $start = $monthDate->startOfMonth();
        $end = $monthDate->endOfMonth()->min(CarbonImmutable::today());
        $employees = User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $minutesByEmployee = TimeEntry::query()
            ->whereIn('user_id', $employees->modelKeys())
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('status', TimeEntry::STATUS_FINAL)
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $entries): int => $entries->sum(
                fn (TimeEntry $entry): int => $entry->netMinutes(),
            ));

        return $employees->map(fn (User $employee): array => [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'month' => $monthDate->format('Y-m'),
            'worked_hours' => number_format(($minutesByEmployee->get($employee->id, 0)) / 60, 2, '.', ''),
        ])->all();
    }

    private function validatedMonth(string $month): CarbonImmutable
    {
        $validated = Validator::make(['month' => $month], [
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.CarbonImmutable::today()->format('Y-m')],
        ])->validate();

        return CarbonImmutable::parse($validated['month'].'-01');
    }

    /**
     * @param  EloquentCollection<int, User>  $employees
     * @return Collection<int, EloquentCollection<int, Absence>>
     */
    private function dashboardAbsences(EloquentCollection $employees): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $today = CarbonImmutable::today()->toDateString();

        return Absence::query()
            ->whereIn('user_id', $employees->modelKeys())
            ->where(function ($query) use ($today): void {
                $query->where('status', Absence::STATUS_PENDING)
                    ->orWhere(function ($query) use ($today): void {
                        $query->where('status', Absence::STATUS_APPROVED)
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today);
                    });
            })
            ->orderBy('start_date')
            ->get()
            ->groupBy('user_id');
    }

    /**
     * @param  Collection<int, Absence>  $absences
     */
    private function statusFor(Collection $absences, CarbonImmutable $today): string
    {
        $pendingSick = $absences->contains(
            fn (Absence $absence): bool => $absence->type === Absence::TYPE_SICK
                && $absence->status === Absence::STATUS_PENDING,
        );
        if ($pendingSick) {
            return 'sick_pending';
        }

        $pendingVacation = $absences->contains(
            fn (Absence $absence): bool => $absence->type === Absence::TYPE_VACATION
                && $absence->status === Absence::STATUS_PENDING,
        );
        if ($pendingVacation) {
            return 'vacation_pending';
        }

        $activeSick = $absences->contains(
            fn (Absence $absence): bool => $absence->type === Absence::TYPE_SICK
                && $absence->status === Absence::STATUS_APPROVED
                && $absence->start_date->toDateString() <= $today->toDateString()
                && $absence->end_date->toDateString() >= $today->toDateString(),
        );
        if ($activeSick) {
            return 'sick';
        }

        $activeVacation = $absences->contains(
            fn (Absence $absence): bool => $absence->type === Absence::TYPE_VACATION
                && $absence->status === Absence::STATUS_APPROVED
                && $absence->start_date->toDateString() <= $today->toDateString()
                && $absence->end_date->toDateString() >= $today->toDateString(),
        );

        return $activeVacation ? 'vacation' : 'ok';
    }
}
