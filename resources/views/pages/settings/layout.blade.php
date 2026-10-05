<div class="settings-layout">
    <nav class="settings-nav" aria-label="{{ __('Settings') }}">
        <x-ui.link class="settings-nav__link {{ request()->routeIs('profile.edit') ? 'is-current' : '' }}" :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</x-ui.link>
        <x-ui.link class="settings-nav__link {{ request()->routeIs('security.edit') ? 'is-current' : '' }}" :href="route('security.edit')" wire:navigate>{{ __('Security') }}</x-ui.link>
        <x-ui.link class="settings-nav__link {{ request()->routeIs('appearance.edit') ? 'is-current' : '' }}" :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</x-ui.link>
    </nav>

    <section class="settings-content">
        <x-ui.heading>{{ $heading ?? '' }}</x-ui.heading>
        <x-ui.subheading>{{ $subheading ?? '' }}</x-ui.subheading>
        <div class="settings-content__body">{{ $slot }}</div>
    </section>
</div>
