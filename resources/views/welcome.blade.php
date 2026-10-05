<x-layouts::auth.simple>
    <section class="welcome-page">
        <header class="welcome-page__intro">
            <p class="welcome-page__eyebrow">{{ __('Company time tracking') }}</p>
            <x-ui.heading level="1" size="xl">{{ __('Welcome to CyTime') }}</x-ui.heading>
            <x-ui.text>{{ __('Record working hours, report absences, and keep track of your leave in one place.') }}</x-ui.text>
        </header>

        <nav class="welcome-page__actions" aria-label="{{ __('Account') }}">
            @auth
                <x-ui.button href="{{ route('dashboard') }}" variant="primary" wire:navigate>
                    {{ __('Go to dashboard') }}
                </x-ui.button>
            @else
                @if (Route::has('login'))
                    <x-ui.button href="{{ route('login') }}" variant="primary" wire:navigate>
                        {{ __('Log in') }}
                    </x-ui.button>
                @endif

                @if (Route::has('register'))
                    <x-ui.button href="{{ route('register') }}" variant="secondary" wire:navigate>
                        {{ __('Create an account') }}
                    </x-ui.button>
                @endif
            @endauth
        </nav>
    </section>
</x-layouts::auth.simple>
