@props(['name' => auth()->user()->name])

@php($menuId = 'user-menu-'.Illuminate\Support\Str::uuid())
<x-ui.dropdown>
    <button
        class="app-user-menu__trigger"
        type="button"
        data-dropdown-toggle
        aria-expanded="false"
        aria-haspopup="true"
        aria-controls="{{ $menuId }}"
        data-test="sidebar-menu-button"
    >
        <x-ui.avatar :name="$name" :initials="auth()->user()->initials()" />
        <span>{{ $name }}</span>
        <x-ui.icon name="chevron-down" />
    </button>
    <div id="{{ $menuId }}" class="app-user-menu__panel" data-dropdown-panel role="menu" hidden>
        <a href="{{ route('profile.edit') }}" wire:navigate role="menuitem">{{ __('Settings') }}</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" role="menuitem" data-test="logout-button">{{ __('Log out') }}</button>
        </form>
    </x-ui.dropdown>
</div>
