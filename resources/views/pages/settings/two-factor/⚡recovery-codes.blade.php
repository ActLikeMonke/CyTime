<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="settings-recovery-codes"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="settings-recovery-codes__header">
        <div class="settings-recovery-codes__title">
            <x-ui.icon name="lock-closed" />
            <x-ui.heading size="lg" level="3">{{ __('2FA recovery codes') }}</x-ui.heading>
        </div>
        <x-ui.text>
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </x-ui.text>
    </div>

    <div class="settings-recovery-codes__content">
        <div class="settings-recovery-codes__actions">
            <x-ui.button
                x-show="!showRecoveryCodes"
                variant="primary"
                leading-icon="eye"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            >
                {{ __('View recovery codes') }}
            </x-ui.button>

            <x-ui.button
                x-show="showRecoveryCodes"
                variant="primary"
                leading-icon="eye-slash"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            >
                {{ __('Hide recovery codes') }}
            </x-ui.button>

            @if (filled($recoveryCodes))
                <x-ui.button
                    x-show="showRecoveryCodes"
                    leading-icon="arrow-path"
                    variant="secondary"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerate codes') }}
                </x-ui.button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="settings-recovery-codes__section"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="settings-recovery-codes__details">
                @error('recoveryCodes')
                    <x-ui.callout variant="danger" icon="x-circle" :heading="$message" />
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="settings-recovery-codes__list"
                        role="list"
                        aria-label="{{ __('Recovery codes') }}"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="settings-recovery-codes__item"
                                wire:loading.class="settings-recovery-codes__item--loading"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <x-ui.text class="settings-recovery-codes__hint">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                    </x-ui.text>
                @endif
            </div>
        </div>
    </div>
</div>
