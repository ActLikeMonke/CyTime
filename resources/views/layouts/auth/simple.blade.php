<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <main class="auth-shell">
            <div class="auth-panel auth-panel--simple">
                <a class="auth-brand" href="{{ route('home') }}" wire:navigate aria-label="{{ config('app.name', 'CyTime') }}">
                    <x-app-logo />
                </a>
                {{ $slot }}
            </div>
        </main>
        @persist('toast-region')
            <x-ui.toast-region />
        @endpersist
    </body>
</html>
