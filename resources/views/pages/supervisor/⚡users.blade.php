<?php

use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\UserRoleService;
use App\Services\LeaveBalanceService;
use App\Services\LeaveEntitlementService;
use App\Services\WorkScheduleService;
use App\Services\WorkTimeRuleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee access')] class extends Component {
    public string $selectedUserId = '';
    public string $weeklyHours = '40';
    public array $workDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
    public string $scheduleValidFrom = '';
    public string $annualLeaveDays = '30';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->scheduleValidFrom = today()->addDay()->toDateString();

        $firstEmployee = User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->orderBy('last_name')
            ->first();

        if ($firstEmployee !== null) {
            $this->selectedUserId = (string) $firstEmployee->id;
            $this->loadSelectedSchedule();
            $this->annualLeaveDays = (string) $firstEmployee->annual_leave_days;
        }
    }

    public function updatedSelectedUserId(): void
    {
        $this->loadSelectedSchedule();
        $target = User::query()->findOrFail($this->selectedUserId);
        $this->annualLeaveDays = (string) $target->annual_leave_days;
    }

    #[Computed]
    public function users()
    {
        Gate::authorize('viewAny', User::class);

        return User::query()->orderBy('last_name')->orderBy('first_name')->get();
    }

    #[Computed]
    public function recentViolations(): array
    {
        $service = app(WorkTimeRuleService::class);
        $violations = [];

        foreach ($this->users as $user) {
            $recent = $service->recentViolations($user);
            if ($recent !== []) {
                $violations[$user->id] = $recent;
            }
        }

        return $violations;
    }

    #[Computed]
    public function leaveBalances(): array
    {
        $service = app(LeaveBalanceService::class);
        $balances = [];

        foreach ($this->users as $user) {
            $balances[$user->id] = $service->for($user, (int) today()->format('Y'));
        }

        return $balances;
    }

    public function updateLeaveEntitlement(): void
    {
        $target = User::query()->findOrFail($this->selectedUserId);
        app(LeaveEntitlementService::class)->update(auth()->user(), $target, $this->annualLeaveDays);
        $this->annualLeaveDays = (string) $target->fresh()->annual_leave_days;
        unset($this->users, $this->leaveBalances);
        $this->dispatch('toast-show', text: __('Annual leave entitlement updated.'));
    }

    public function promote(int $userId): void
    {
        $target = User::query()->findOrFail($userId);
        Gate::authorize('changeRole', $target);

        app(UserRoleService::class)->changeRole(auth()->user(), $target, User::ROLE_SUPERVISOR);

        unset($this->users);
        $this->dispatch('user-role-updated');
    }

    public function demote(int $userId): void
    {
        $target = User::query()->findOrFail($userId);
        Gate::authorize('changeRole', $target);

        app(UserRoleService::class)->changeRole(auth()->user(), $target, User::ROLE_EMPLOYEE);

        unset($this->users);
        $this->dispatch('user-role-updated');
    }

    public function updateSchedule(): void
    {
        $target = User::query()->findOrFail($this->selectedUserId);
        Gate::authorize('updateSchedule', $target);

        app(WorkScheduleService::class)->change(
            auth()->user(),
            $target,
            $this->weeklyHours,
            $this->workDays,
            $this->scheduleValidFrom,
        );

        $this->dispatch('toast-show', text: __('Working schedule updated.'));
    }

    private function loadSelectedSchedule(): void
    {
        if ($this->selectedUserId === '') {
            return;
        }

        $target = User::query()->findOrFail($this->selectedUserId);
        Gate::authorize('updateSchedule', $target);

        $schedule = $target->workSchedules()
            ->whereDate('valid_from', '<=', today())
            ->orderByDesc('valid_from')
            ->first();

        if ($schedule instanceof WorkSchedule) {
            $this->weeklyHours = (string) $schedule->weekly_hours;
            $this->workDays = $schedule->work_days;
        }

        $this->scheduleValidFrom = today()->addDay()->toDateString();
    }
}; ?>

<div class="supervisor-users">
        <header class="supervisor-users__header">
            <div class="app-page-title-row">
                <x-ui.heading level="1" size="xl">{{ __('Employee access') }}</x-ui.heading>
                <x-app-page-actions />
            </div>
            <x-ui.text>{{ __('Manage employee roles and dated working schedules.') }}</x-ui.text>
        </header>

        <section class="supervisor-users__table-scroll">
            <table class="supervisor-users__table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Recent rule warnings') }}</th>
                        <th>{{ __('Leave balance') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td>
                                @if (isset($this->recentViolations[$user->id]))
                                    <span class="supervisor-users__warning" aria-label="{{ __('Working-time rule warning') }}">
                                        ⚠ {{ __(':count flagged entries', ['count' => count($this->recentViolations[$user->id])]) }}
                                    </span>
                                    <ul class="supervisor-users__violation-list">
                                        @foreach ($this->recentViolations[$user->id] as $violation)
                                            <li>{{ $violation['date'] }}: {{ collect($violation['warnings'])->pluck('rule')->unique()->implode(', ') }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="supervisor-users__muted">{{ __('None') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $this->leaveBalances[$user->id]['remaining'] }} {{ __('days remaining') }}
                                <span class="supervisor-users__leave-details">
                                    {{ __(':used used · :pending pending', [
                                        'used' => $this->leaveBalances[$user->id]['used'],
                                        'pending' => $this->leaveBalances[$user->id]['pending'],
                                    ]) }}
                                </span>
                            </td>
                            <td>
                                @if ($user->isEmployee())
                                    <x-ui.button size="sm" wire:click="promote({{ $user->id }})">
                                        {{ __('Promote to supervisor') }}
                                    </x-ui.button>
                                @elseif ($this->users->where('role', \App\Models\User::ROLE_SUPERVISOR)->count() > 1)
                                    <x-ui.button size="sm" variant="danger" wire:click="demote({{ $user->id }})">
                                        {{ __('Demote to employee') }}
                                    </x-ui.button>
                                @else
                                    <x-ui.text>{{ __('Only supervisor') }}</x-ui.text>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="supervisor-users__form-panel">
            <div class="supervisor-users__panel-heading">
                <x-ui.heading level="2">{{ __('Change annual leave entitlement') }}</x-ui.heading>
                <x-ui.text>{{ __('Update the selected employee’s annual entitlement. The change is recorded in the audit log.') }}</x-ui.text>
            </div>
            <form wire:submit="updateLeaveEntitlement" class="supervisor-users__form">
                <x-ui.select wire:model.live="selectedUserId" :label="__('Employee')" required>
                    @foreach ($this->users->where('role', \App\Models\User::ROLE_EMPLOYEE) as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input wire:model="annualLeaveDays" :label="__('Annual leave days')" type="number" min="0" max="255" required />
                @error('days')
                    <p class="supervisor-users__error" role="alert">{{ $message }}</p>
                @enderror
                <x-ui.button variant="primary" type="submit" :disabled="$this->users->where('role', \App\Models\User::ROLE_EMPLOYEE)->isEmpty()">
                    {{ __('Save entitlement') }}
                </x-ui.button>
            </form>
        </section>

        <section class="supervisor-users__form-panel">
            <div class="supervisor-users__panel-heading">
                <x-ui.heading level="2">{{ __('Change working schedule') }}</x-ui.heading>
                <x-ui.text>{{ __('A new schedule preserves history and applies from its effective date. Leave entitlement is not recalculated automatically.') }}</x-ui.text>
            </div>

            <form wire:submit="updateSchedule" class="supervisor-users__form">
                <x-ui.select wire:model.live="selectedUserId" :label="__('Employee')" required>
                    @foreach ($this->users->where('role', \App\Models\User::ROLE_EMPLOYEE) as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input
                    wire:model="weeklyHours"
                    :label="__('Weekly working hours')"
                    type="number"
                    min="0.01"
                    max="40"
                    step="0.01"
                    required
                />

                <x-ui.input wire:model="scheduleValidFrom" :label="__('Effective from')" type="date" required />

                <fieldset class="supervisor-users__workdays">
                    <legend>{{ __('Workdays') }}</legend>
                    @foreach (['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday')] as $day => $label)
                        <label class="supervisor-users__workday-option">
                            <input type="checkbox" wire:model="workDays" value="{{ $day }}" />
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>

                @error('weeklyHours')
                    <p class="supervisor-users__error" role="alert">{{ $message }}</p>
                @enderror
                @error('workDays')
                    <p class="supervisor-users__error" role="alert">{{ $message }}</p>
                @enderror
                @error('workDays.*')
                    <p class="supervisor-users__error" role="alert">{{ $message }}</p>
                @enderror
                @error('validFrom')
                    <p class="supervisor-users__error" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button variant="primary" type="submit" :disabled="$this->users->where('role', \App\Models\User::ROLE_EMPLOYEE)->isEmpty()">
                    {{ __('Save schedule') }}
                </x-ui.button>
            </form>
        </section>
</div>
