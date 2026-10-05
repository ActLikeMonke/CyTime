<x-layouts::app :title="__('Dashboard')">
    <div class="dashboard-page">
        <div class="dashboard-page__intro">
            <div class="app-page-title-row">
                <x-ui.heading level="1" size="xl">{{ __('Welcome, :name', ['name' => auth()->user()->first_name]) }}</x-ui.heading>
                <x-app-page-actions />
            </div>
            <x-ui.text>{{ __('Track your working hours, review your weekly balance, and manage your time entries.') }}</x-ui.text>
        </div>
        <div class="dashboard-page__actions">
            <x-ui.button :href="route('time.index')" variant="primary" wire:navigate>{{ __('Open time tracking') }}</x-ui.button>
            @if (auth()->user()->isSupervisor())
                <x-ui.button :href="route('supervisor.dashboard')" wire:navigate>{{ __('Supervisor dashboard') }}</x-ui.button>
            @endif
        </div>
    </div>
</x-layouts::app>
