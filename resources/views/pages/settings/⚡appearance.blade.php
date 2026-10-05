<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="settings-appearance">
    @include('partials.settings-heading')

    <x-ui.heading level="2" class="visually-hidden">{{ __('Appearance settings') }}</x-ui.heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="appearance-choices" role="group" aria-label="{{ __('Color theme') }}">
            <x-ui.button variant="secondary" type="button" data-appearance-choice="light" aria-pressed="false">
                <x-ui.icon name="sun" />{{ __('Light') }}
            </x-ui.button>
            <x-ui.button variant="secondary" type="button" data-appearance-choice="dark" aria-pressed="false">
                <x-ui.icon name="moon" />{{ __('Dark') }}
            </x-ui.button>
            <x-ui.button variant="secondary" type="button" data-appearance-choice="system" aria-pressed="true">
                <x-ui.icon name="computer-desktop" />{{ __('System') }}
            </x-ui.button>
        </div>
    </x-pages::settings.layout>
</section>
