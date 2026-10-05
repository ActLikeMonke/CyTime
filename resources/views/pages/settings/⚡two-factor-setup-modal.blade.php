<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    public bool $modalOpen = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        $this->modalOpen = true;

        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
            'modalOpen',
        );

        $this->resetErrorBag();
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<x-ui.modal
    name="two-factor-setup-modal"
    @close="$wire.closeModal()"
    :aria-label="__('Two-factor setup')"
    wire:model="modalOpen"
>
    <div class="settings-two-factor-setup settings-two-factor-setup__modal">
        <header class="settings-two-factor-setup__header">
            <div class="settings-two-factor-setup__illustration" aria-hidden="true">
                <x-ui.icon name="qr-code" />
            </div>
            <div class="settings-two-factor-setup__description">
                <x-ui.heading size="lg">{{ $this->modalConfig['title'] }}</x-ui.heading>
                <x-ui.text>{{ $this->modalConfig['description'] }}</x-ui.text>
            </div>
        </header>

        @if ($showVerificationStep)
            <div class="settings-two-factor-setup__verification">
                <div
                    class="settings-two-factor-setup__otp"
                    x-data
                    x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                >
                    <x-ui.otp
                        name="code"
                        wire:model="code"
                        length="6"
                        label="OTP Code"
                    />
                </div>

                <div class="settings-two-factor-setup__actions">
                    <x-ui.button
                        variant="secondary"
                        class="settings-two-factor-setup__action"
                        wire:click="resetVerification"
                    >
                        {{ __('Back') }}
                    </x-ui.button>

                    <x-ui.button
                        variant="primary"
                        class="settings-two-factor-setup__action"
                        wire:click="confirmTwoFactor"
                        x-bind:disabled="$wire.code.length < 6"
                    >
                        {{ __('Confirm') }}
                    </x-ui.button>
                </div>
            </div>
        @else
            @error('setupData')
                <x-ui.callout variant="danger" icon="x-circle" :heading="$message" />
            @enderror

            <div class="settings-two-factor-setup__qr">
                @empty($qrCodeSvg)
                    <div class="settings-two-factor-setup__loading" role="status" aria-label="{{ __('Loading QR code') }}">
                        <x-ui.icon name="arrow-path" />
                    </div>
                @else
                    <div
                        class="settings-two-factor-setup__qr-image"
                        :style="document.documentElement.classList.contains('dark') ? 'filter: invert(1) brightness(1.5)' : ''"
                    >
                        {!! $qrCodeSvg !!}
                    </div>
                @endempty
            </div>

            <x-ui.button
                :disabled="$errors->has('setupData')"
                variant="primary"
                class="settings-two-factor-setup__continue"
                wire:click="showVerificationIfNecessary"
            >
                {{ $this->modalConfig['buttonText'] }}
            </x-ui.button>

            <div class="settings-two-factor-setup__manual">
                <x-ui.separator class="settings-two-factor-setup__separator" />
                <p class="settings-two-factor-setup__manual-label">
                    {{ __('or, enter the code manually') }}
                </p>

                <div
                    class="settings-two-factor-setup__setup-key"
                    x-data="{
                        copied: false,
                        async copy() {
                            try {
                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {
                                console.warn('Could not copy to clipboard');
                            }
                        }
                    }"
                >
                    @empty($manualSetupKey)
                        <div class="settings-two-factor-setup__loading" role="status" aria-label="{{ __('Loading setup key') }}">
                            <x-ui.icon name="arrow-path" />
                        </div>
                    @else
                        <x-ui.input
                            class="settings-two-factor-setup__setup-key-input"
                            type="text"
                            readonly
                            :value="$manualSetupKey"
                            :aria-label="__('Authenticator setup key')"
                        />

                        <x-ui.button
                            class="settings-two-factor-setup__copy"
                            variant="secondary"
                            type="button"
                            @click="copy()"
                            :aria-label="__('Copy setup key')"
                        >
                            <x-ui.icon name="document-duplicate" x-show="!copied" />
                            <x-ui.icon name="check" x-show="copied" />
                        </x-ui.button>
                    @endempty
                </div>
            </div>
        @endif
    </div>
</x-ui.modal>
