<?php

use App\Models\User;
use App\Services\SupervisorDashboardService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Supervisor dashboard')] class extends Component
{
    public string $filter = 'all';

    public string $sort = 'name';

    public string $direction = 'asc';

    public string $exportMonth = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->exportMonth = today()->format('Y-m');
    }

    #[Computed]
    public function employees(): array
    {
        Gate::authorize('viewAny', User::class);

        return app(SupervisorDashboardService::class)->overview(
            $this->filter,
            $this->sort,
            $this->direction,
        );
    }

    public function exportMonthlyHours(): StreamedResponse
    {
        Gate::authorize('viewAny', User::class);
        $rows = app(SupervisorDashboardService::class)->monthlyHoursRows($this->exportMonth);
        $month = $this->exportMonth;

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to open the CSV output stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Employee ID', 'Employee name', 'Month', 'Worked hours'], ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($output, array_values($row), ',', '"', '\\');
            }

            fclose($output);
        }, "cytime-monthly-hours-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}; ?>

<div class="supervisor-dashboard">
    <header class="supervisor-dashboard__header">
        <div class="app-page-title-row">
            <x-ui.heading level="1" size="xl">{{ __('Supervisor dashboard') }}</x-ui.heading>
            <x-app-page-actions />
        </div>
        <x-ui.text>{{ __('Review employee status, leave balances, and hour balances.') }}</x-ui.text>
    </header>

    <section class="supervisor-dashboard__filters">
        <div class="supervisor-dashboard__filter-layout">
            <div class="supervisor-dashboard__filter-controls">
                <x-ui.select wire:model.live="filter" :label="__('Show accounts')">
                    <option value="all">{{ __('All employees') }}</option>
                    <option value="open">{{ __('Open requests only') }}</option>
                    <option value="accounts">{{ __('All accounts') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="sort" :label="__('Sort by')">
                    <option value="name">{{ __('Name') }}</option>
                    <option value="remaining_leave_days">{{ __('Leave days left') }}</option>
                    <option value="used_leave_days">{{ __('Leave days used') }}</option>
                    <option value="status">{{ __('Status') }}</option>
                    <option value="hour_balance_minutes">{{ __('Hour balance') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="direction" :label="__('Order')">
                    <option value="asc">{{ __('Ascending') }}</option>
                    <option value="desc">{{ __('Descending') }}</option>
                </x-ui.select>
            </div>

            <form wire:submit="exportMonthlyHours" class="supervisor-dashboard__export-form">
                <x-ui.input wire:model="exportMonth" :label="__('Export month')" type="month" :max="today()->format('Y-m')" required />
                <x-ui.button type="submit" variant="primary">{{ __('Export monthly CSV') }}</x-ui.button>
            </form>
        </div>
    </section>

    <section class="supervisor-dashboard__table-scroll">
        <table class="supervisor-dashboard__table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Leave balance') }}</th>
                    <th>{{ __('Leave days used') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Hour balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->employees as $row)
                    <tr wire:key="dashboard-employee-{{ $row['user']->id }}">
                        <td>
                            <x-ui.link href="{{ route('supervisor.users.show', $row['user']) }}" wire:navigate class="supervisor-dashboard__employee-link">
                                {{ $row['user']->name }}
                            </x-ui.link>
                            @if ($row['user']->isSupervisor())
                                <x-ui.text>{{ __('Supervisor') }}</x-ui.text>
                            @endif
                        </td>
                        <td>
                            {{ __(':days days remaining', ['days' => $row['remaining_leave_days']]) }}
                            <span class="supervisor-users__leave-details">
                                {{ __(':used used · :pending pending', [
                                    'used' => $row['used_leave_days'],
                                    'pending' => $row['pending_leave_days'],
                                ]) }}
                            </span>
                        </td>
                        <td>{{ $row['used_leave_days'] }}</td>
                        <td>
                            @php($statusLabel = match ($row['status']) {
                                'sick_pending' => __('Sick – approval required'),
                                'vacation_pending' => __('Leave request pending'),
                                'sick' => __('Sick'),
                                'vacation' => __('On vacation'),
                                default => __('OK'),
                            })
                            <span @class([
                                'supervisor-dashboard__status',
                                'supervisor-dashboard__status--pending' => $row['has_open_requests'],
                                'supervisor-dashboard__status--sick' => $row['status'] === 'sick',
                                'supervisor-dashboard__status--ok' => $row['status'] === 'ok',
                            ])>{{ $statusLabel }}</span>
                        </td>
                        <td class="supervisor-dashboard__balance {{ $row['hour_balance_minutes'] < 0 ? 'supervisor-dashboard__balance--negative' : 'supervisor-dashboard__balance--positive' }}">
                            {{ $row['hour_balance_minutes'] > 0 ? '+' : '' }}{{ number_format($row['hour_balance_minutes'] / 60, 2) }} h
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="supervisor-dashboard__empty-state">{{ __('No employees match this filter.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
