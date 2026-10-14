<?php

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LeaveBalanceService;
use App\Services\LeaveEntitlementService;
use App\Services\SupervisorDashboardService;
use App\Services\UserRoleService;
use App\Services\WorkScheduleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee details')] class extends Component
{
    #[Locked]
    public int $employeeId;

    public string $month = '';

    public string $annualLeaveDays = '30';

    public string $weeklyHours = '40';

    public array $workDays = ['mon', 'tue', 'wed', 'thu', 'fri'];

    public string $scheduleValidFrom = '';

    public function mount(User $user): void
    {
        Gate::authorize('viewAny', User::class);
        Gate::authorize('view', $user);
        $this->employeeId = $user->id;
        $this->month = today()->format('Y-m');
        $this->annualLeaveDays = (string) $user->annual_leave_days;
        $this->scheduleValidFrom = today()->addDay()->toDateString();
        $schedule = $user->workSchedules()->whereDate('valid_from', '<=', today())->orderByDesc('valid_from')->first();
        if ($schedule instanceof WorkSchedule) {
            $this->weeklyHours = (string) $schedule->weekly_hours;
            $this->workDays = $schedule->work_days;
        }
    }

    #[Computed]
    public function employee(): User
    {
        Gate::authorize('viewAny', User::class);
        $employee = User::query()->findOrFail($this->employeeId);
        Gate::authorize('view', $employee);

        return $employee;
    }

    #[Computed]
    public function report(): array
    {
        return app(SupervisorDashboardService::class)->employeeMonth($this->employee, $this->month);
    }

    #[Computed]
    public function leaveBalance(): array
    {
        return app(LeaveBalanceService::class)->for($this->employee, (int) today()->format('Y'));
    }

    #[Computed]
    public function absences()
    {
        return $this->employee->absences()->orderByDesc('start_date')->get();
    }

    #[Computed]
    public function schedules()
    {
        return $this->employee->workSchedules()->orderByDesc('valid_from')->get();
    }

    #[Computed]
    public function canDemote(): bool
    {
        Gate::authorize('viewAny', User::class);

        return User::query()->where('role', User::ROLE_SUPERVISOR)->whereNull('deactivated_at')->count() > 1;
    }

    public function updateLeaveEntitlement(): void
    {
        $target = app(LeaveEntitlementService::class)->update(auth()->user(), $this->employee, $this->annualLeaveDays);
        $this->annualLeaveDays = (string) $target->annual_leave_days;
        unset($this->employee, $this->leaveBalance, $this->report);
        $this->dispatch('toast-show', text: __('Annual leave entitlement updated.'));
    }

    public function updateSchedule(): void
    {
        app(WorkScheduleService::class)->change(
            auth()->user(), $this->employee, $this->weeklyHours, $this->workDays, $this->scheduleValidFrom,
        );
        unset($this->schedules, $this->leaveBalance, $this->report);
        $this->dispatch('toast-show', text: __('Working schedule updated.'));
    }

    public function promote(): void
    {
        $this->changeRole(User::ROLE_SUPERVISOR);
    }

    public function demote(): void
    {
        $this->changeRole(User::ROLE_EMPLOYEE);
    }

    private function changeRole(string $role): void
    {
        app(UserRoleService::class)->changeRole(auth()->user(), $this->employee, $role);
        unset($this->employee, $this->report, $this->canDemote);
        $this->dispatch('toast-show', text: __('Employee role updated.'));
        if (! auth()->user()->fresh()->isSupervisor()) {
            $this->redirectRoute('time.index', navigate: true);
        }
    }
}; ?>

<div class="supervisor-detail">
    <header class="supervisor-detail__header">
        <div class="supervisor-detail__intro">
            <div class="app-page-title-row">
                <x-ui.heading level="1" size="xl">{{ $this->report['employee']->name }}</x-ui.heading>
                <x-app-page-actions />
            </div>
            <x-ui.text>{{ __('Employee details · :email', ['email' => $this->report['employee']->email]) }}</x-ui.text>
        </div>
        <x-ui.button :href="route('supervisor.dashboard')" wire:navigate>{{ __('Back to supervisor dashboard') }}</x-ui.button>
    </header>

    <section class="supervisor-detail__section">
        <x-ui.heading level="2">{{ __('Account role') }}</x-ui.heading>
        <x-ui.text>{{ __('Role') }}: {{ $this->employee->role }}</x-ui.text>
        @if ($this->employee->isEmployee())
            <x-ui.button wire:click="promote">{{ __('Promote to supervisor') }}</x-ui.button>
        @elseif ($this->canDemote)
            <x-ui.button variant="danger" wire:click="demote">{{ __('Demote to employee') }}</x-ui.button>
        @else
            <x-ui.text>{{ __('Only supervisor') }}</x-ui.text>
        @endif
        @error('role')<p class="supervisor-users__error" role="alert">{{ $message }}</p>@enderror
    </section>

    <section class="supervisor-detail__summary" aria-label="{{ __('Leave balance') }}">
        @foreach (['entitlement' => __('Annual entitlement'), 'carry_over' => __('Carry-over'), 'used' => __('Used leave'), 'pending' => __('Pending leave'), 'remaining' => __('Leave balance')] as $key => $label)
            <div class="supervisor-detail__summary-card">
                <x-ui.text>{{ $label }}</x-ui.text>
                <x-ui.heading level="2">{{ $this->leaveBalance[$key] }} {{ __('days') }}</x-ui.heading>
            </div>
        @endforeach
    </section>

    <section class="supervisor-users__form-panel">
        <x-ui.heading level="2">{{ __('Change annual leave entitlement') }}</x-ui.heading>
        <x-ui.text>{{ __('Changes apply to this employee and are recorded in the audit log.') }}</x-ui.text>
        <form wire:submit="updateLeaveEntitlement" class="supervisor-users__form">
            <x-ui.input wire:model="annualLeaveDays" :label="__('Annual leave days')" type="number" min="0" max="255" required />
            @error('days')<p class="supervisor-users__error" role="alert">{{ $message }}</p>@enderror
            <x-ui.button variant="primary" type="submit">{{ __('Save entitlement') }}</x-ui.button>
        </form>
    </section>

    <section class="supervisor-users__form-panel">
        <x-ui.heading level="2">{{ __('Change working schedule') }}</x-ui.heading>
        <x-ui.text>{{ __('A new schedule preserves history and applies from its effective date. Leave entitlement is not recalculated automatically.') }}</x-ui.text>
        <form wire:submit="updateSchedule" class="supervisor-users__form">
            <x-ui.input wire:model="weeklyHours" :label="__('Weekly working hours')" type="number" min="0.01" max="40" step="0.01" required />
            <x-ui.input wire:model="scheduleValidFrom" :label="__('Effective from')" type="date" required />
            <fieldset class="supervisor-users__workdays">
                <legend>{{ __('Workdays') }}</legend>
                @foreach (['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday')] as $day => $label)
                    <x-ui.checkbox wire:model="workDays" :value="$day" :label="$label" />
                @endforeach
            </fieldset>
            @foreach (['weeklyHours', 'workDays', 'workDays.*', 'validFrom'] as $field)
                @error($field)<p class="supervisor-users__error" role="alert">{{ $message }}</p>@enderror
            @endforeach
            <x-ui.button variant="primary" type="submit">{{ __('Save schedule') }}</x-ui.button>
        </form>
    </section>

    <section class="supervisor-detail__section">
        <x-ui.heading level="2">{{ __('Working schedule history') }}</x-ui.heading>
        @forelse ($this->schedules as $schedule)
            <p wire:key="schedule-{{ $schedule->id }}">
                {{ $schedule->valid_from->toDateString() }} · {{ $schedule->weekly_hours }} {{ __('hours per week') }} ·
                {{ collect($schedule->work_days)->map(fn (string $day): string => __(ucfirst($day)))->implode(', ') }}
            </p>
        @empty
            <x-ui.text>{{ __('No working schedule recorded.') }}</x-ui.text>
        @endforelse
    </section>

    <section class="supervisor-detail__month-controls">
        <div class="supervisor-detail__month-copy">
            <x-ui.heading level="2">{{ __('Monthly record') }}</x-ui.heading>
            <x-ui.text>{{ __('Time entries and absences for the selected month.') }}</x-ui.text>
        </div>
        <x-ui.input wire:model.live.debounce.1000ms="month" :label="__('Month')" type="month" :max="today()->format('Y-m')" required />
    </section>

    <section class="supervisor-detail__summary">
        <div class="supervisor-detail__summary-card">
            <x-ui.text>{{ __('Worked this month') }}</x-ui.text>
            <x-ui.heading level="2">{{ number_format($this->report['total_minutes'] / 60, 2) }} h</x-ui.heading>
        </div>
        <div class="supervisor-detail__summary-card">
            <x-ui.text>{{ __('Recorded time entries') }}</x-ui.text>
            <x-ui.heading level="2">{{ $this->report['entries']->count() }}</x-ui.heading>
        </div>
    </section>

    <section class="supervisor-detail__section">
        <x-ui.heading level="2">{{ __('Weekly balances') }}</x-ui.heading>
        <div class="supervisor-detail__weekly-balances">
            @forelse ($this->report['weekly_balances'] as $week)
                <article wire:key="week-{{ $week['week_start'] }}" class="supervisor-detail__week-card">
                    <x-ui.text>{{ $week['week_start'] }} – {{ $week['week_end'] }}</x-ui.text>
                    <x-ui.text>{{ __('Target') }}: {{ number_format($week['target_minutes'] / 60, 2) }} h</x-ui.text>
                    <x-ui.text>{{ __('Worked') }}: {{ number_format($week['worked_minutes'] / 60, 2) }} h</x-ui.text>
                    <x-ui.text>{{ __('Balance') }}:
                        <span class="{{ $week['balance_minutes'] < 0 ? 'supervisor-detail__balance--negative' : 'supervisor-detail__balance--positive' }}">
                            {{ $week['balance_minutes'] > 0 ? '+' : '' }}{{ number_format($week['balance_minutes'] / 60, 2) }} h
                        </span>
                    </x-ui.text>
                </article>
            @empty
                <x-ui.text>{{ __('No weekly balance is available for this month.') }}</x-ui.text>
            @endforelse
        </div>
    </section>

    <section class="supervisor-detail__section">
        <x-ui.heading level="2">{{ __('Time entries') }}</x-ui.heading>
        <div class="supervisor-detail__table-scroll">
            <table class="supervisor-detail__table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Break') }}</th>
                        <th>{{ __('Net') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->report['entries'] as $entry)
                        <tr wire:key="entry-{{ $entry->id }}">
                            <td>{{ $entry->date->toDateString() }}</td>
                            <td>{{ substr((string) $entry->start_time, 0, 5) }} – {{ $entry->end_time ? substr((string) $entry->end_time, 0, 5) : '—' }}</td>
                            <td>{{ $entry->break_minutes }} min</td>
                            <td>{{ number_format($entry->netMinutes() / 60, 2) }} h</td>
                            <td>{{ $entry->status === TimeEntry::STATUS_IN_PROGRESS ? __('Clocked in') : ucfirst(str_replace('_', ' ', $entry->status)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="supervisor-detail__empty-state">{{ __('No time entries for this month.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="supervisor-detail__section">
        <x-ui.heading level="2">{{ __('Absences') }}</x-ui.heading>
        <x-ui.text>{{ __('All recorded leave and sick-day dates, including future requests.') }}</x-ui.text>
        <div class="supervisor-detail__table-scroll">
            <table class="supervisor-detail__table">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Sick note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->absences as $absence)
                        <tr wire:key="absence-{{ $absence->id }}">
                            <td>{{ ucfirst($absence->type) }}</td>
                            <td>{{ $absence->start_date->toDateString() }} – {{ $absence->end_date->toDateString() }}</td>
                            <td>{{ ucfirst($absence->status) }}</td>
                            <td>{{ $absence->type === Absence::TYPE_SICK ? ($absence->sick_note_submitted ? __('Submitted') : __('Not submitted')) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="supervisor-detail__empty-state">{{ __('No absences recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
