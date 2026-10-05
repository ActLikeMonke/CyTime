<div class="app-page-actions">
    <button class="ui-button ui-button--ghost app-page-actions__menu" type="button" data-sidebar-toggle aria-controls="primary-navigation" aria-expanded="false" aria-label="{{ __('Open navigation') }}" title="{{ __('Open navigation') }}">
        <x-ui.icon name="bars-3" />
    </button>
    <a class="ui-button ui-button--ghost app-page-actions__settings" href="{{ route('profile.edit') }}" wire:navigate aria-label="{{ __('Settings') }}" title="{{ __('Settings') }}">
        <x-ui.icon name="cog-6-tooth" />
    </a>
</div>
