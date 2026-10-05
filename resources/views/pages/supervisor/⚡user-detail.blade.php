<?php

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\SupervisorDashboardService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee details')] class extends Component
{
    public int $employeeId;

    public string $month = '';

    public function mount(User $user): void
    {
        Gate::authorize('viewAny', User::class);
        Gate::authorize('view', $user);
        abort_unless($user->isEmployee(), 404);

        $this->employeeId = $user->id;
        $this->month = today()->format('Y-m');
    }

    #[Computed]
    public function report(): array
    {
        Gate::authorize('viewAny', User::class);
        $employee = User::query()->findOrFail($this->employeeId);
        Gate::authorize('view', $employee);
        abort_unless($employee->isEmployee(), 404);

        return app(SupervisorDashboardService::class)->employeeMonth($employee, $this->month);
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

    <section class="supervisor-detail__month-controls">
        <div class="supervisor-detail__month-copy">
            <x-ui.heading level="2">{{ __('Monthly record') }}</x-ui.heading>
            <x-ui.text>{{ __('Time entries and absences for the selected month.') }}</x-ui.text>
        </div>
        <x-ui.input wire:model.live="month" :label="__('Month')" type="month" :max="today()->format('Y-m')" required />
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
                            <td>{{ ucfirst(str_replace('_', ' ', $entry->status)) }}</td>
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
                    @forelse ($this->report['absences'] as $absence)
                        <tr wire:key="absence-{{ $absence->id }}">
                            <td>{{ ucfirst($absence->type) }}</td>
                            <td>{{ $absence->start_date->toDateString() }} – {{ $absence->end_date->toDateString() }}</td>
                            <td>{{ ucfirst($absence->status) }}</td>
                            <td>{{ $absence->type === Absence::TYPE_SICK ? ($absence->sick_note_submitted ? __('Submitted') : __('Not submitted')) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="supervisor-detail__empty-state">{{ __('No absences overlap this month.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
