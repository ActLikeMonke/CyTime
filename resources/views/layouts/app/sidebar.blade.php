<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <div class="app-shell">
            <button class="app-sidebar__scrim" type="button" data-sidebar-toggle aria-expanded="false" aria-label="{{ __('Close navigation') }}"></button>
            <aside class="app-sidebar" id="primary-navigation">
                <div class="app-sidebar__brand">
                    <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
                    <button class="ui-button ui-button--ghost ui-button--sm app-sidebar__close" type="button" data-sidebar-toggle aria-expanded="false" aria-label="{{ __('Close navigation') }}">
                        <x-ui.icon name="x-mark" />
                    </button>
                </div>
                <nav class="app-sidebar__nav" aria-label="{{ __('Main navigation') }}">
                    <p class="app-sidebar__label">{{ __('Platform') }}</p>
                    <a class="app-nav-link {{ request()->routeIs('dashboard') ? 'is-current' : '' }}" href="{{ route('dashboard') }}" wire:navigate>
                        <x-ui.icon name="home" />{{ __('Dashboard') }}
                    </a>
                    <a class="app-nav-link {{ request()->routeIs('time.index') ? 'is-current' : '' }}" href="{{ route('time.index') }}" wire:navigate>
                        <x-ui.icon name="clock" />{{ __('My time') }}
                    </a>
                    @if (auth()->user()->isEmployee())
                        <a class="app-nav-link {{ request()->routeIs('time.calendar') ? 'is-current' : '' }}" href="{{ route('time.calendar') }}" wire:navigate>
                            <x-ui.icon name="calendar-days" />{{ __('My time calendar') }}
                        </a>
                    @endif
                    <a class="app-nav-link {{ request()->routeIs('absences.*') ? 'is-current' : '' }}" href="{{ route('absences.index') }}" wire:navigate>
                        <x-ui.icon name="calendar" />{{ __('Absences and leave') }}
                    </a>
                    @if (auth()->user()->isSupervisor())
                        <p class="app-sidebar__label">{{ __('Supervision') }}</p>
                        <a class="app-nav-link {{ request()->routeIs('supervisor.dashboard', 'supervisor.users.*') ? 'is-current' : '' }}" href="{{ route('supervisor.dashboard') }}" wire:navigate>
                            <x-ui.icon name="chart-bar" />{{ __('Supervisor dashboard') }}
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('supervisor.absences.*') ? 'is-current' : '' }}" href="{{ route('supervisor.absences.index') }}" wire:navigate>
                            <x-ui.icon name="clipboard-document-check" />{{ __('Absence approvals') }}
                        </a>
                    @endif
                </nav>
                <div class="app-sidebar__footer">
                    <a class="app-nav-link" href="{{ route('profile.edit') }}" wire:navigate>
                        <x-ui.avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                        <span class="app-user-summary">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>{{ auth()->user()->email }}</small>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="app-nav-link app-nav-link--button" type="submit" data-test="logout-button">
                            <x-ui.icon name="arrow-right-start-on-rectangle" />{{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </aside>
            <div class="app-content">
                <main class="app-main">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @persist('toast-region')
            <x-ui.toast-region />
        @endpersist
    </body>
</html>
