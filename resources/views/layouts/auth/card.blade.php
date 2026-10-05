<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <main class="auth-shell">
            <div class="auth-panel">
                <a class="auth-brand" href="{{ route('home') }}" wire:navigate aria-label="{{ config('app.name', 'CyTime') }}">
                    <x-app-logo />
                </a>
                <div class="auth-panel__content">{{ $slot }}</div>
            </div>
        </main>
        @persist('toast-region')
            <x-ui.toast-region />
        @endpersist
    </body>
</html>
