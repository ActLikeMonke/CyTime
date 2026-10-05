<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="settings-delete-account">
    <div class="settings-delete-account__intro">
        <x-ui.heading>{{ __('Deactivate account') }}</x-ui.heading>
        <x-ui.subheading>{{ __('Deactivate your account and retain official records as required') }}</x-ui.subheading>
    </div>

    <x-ui.modal-trigger name="confirm-user-deletion">
        <x-ui.button variant="danger" data-test="delete-user-button">
            {{ __('Deactivate account') }}
        </x-ui.button>
    </x-ui.modal-trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
