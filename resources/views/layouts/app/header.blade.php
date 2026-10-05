<header class="app-header">
    <a class="app-brand" href="{{ route('dashboard') }}" wire:navigate>
        <span class="app-brand__mark" aria-hidden="true">C</span>
        <span>{{ config('app.name', 'CyTime') }}</span>
    </a>
    {{ $slot ?? '' }}
</header>
