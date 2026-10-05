<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast-show', type: 'success', text: __('Password updated.'));
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="settings-security">
    @include('partials.settings-heading')

    <x-ui.heading level="2" class="visually-hidden">{{ __('Security settings') }}</x-ui.heading>

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="settings-security__password-form">
            <x-ui.password-input
                wire:model="current_password"
                error-key="current_password"
                :label="__('Current password')"
                required
                autocomplete="current-password"
            />
            <x-ui.password-input
                wire:model="password"
                error-key="password"
                :label="__('New password')"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />
            <x-ui.password-input
                wire:model="password_confirmation"
                error-key="password_confirmation"
                :label="__('Confirm password')"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <div class="settings-security__actions">
                <x-ui.button variant="primary" type="submit" data-test="update-password-button">
                    {{ __('Save') }}
                </x-ui.button>
            </div>
        </form>

        @if ($canManageTwoFactor)
            <section class="settings-security__two-factor">
                <x-ui.heading>{{ __('Two-factor authentication') }}</x-ui.heading>
                <x-ui.subheading>{{ __('Manage your two-factor authentication settings') }}</x-ui.subheading>

                <div class="settings-security__section-content" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="settings-security__section-description">
                            <x-ui.text>
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </x-ui.text>

                            <div class="settings-security__actions">
                                <x-ui.button variant="danger" wire:click="disable">
                                    {{ __('Disable 2FA') }}
                                </x-ui.button>
                            </div>

                            <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="settings-security__section-description">
                            <x-ui.text>
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </x-ui.text>

                            <x-ui.modal-trigger name="two-factor-setup-modal">
                                <x-ui.button variant="primary" wire:click="$dispatch('start-two-factor-setup')">
                                    {{ __('Enable 2FA') }}
                                </x-ui.button>
                            </x-ui.modal-trigger>

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($canManagePasskeys)
            <section class="settings-security__passkeys">
                <x-ui.heading>{{ __('Passkeys') }}</x-ui.heading>
                <x-ui.subheading>{{ __('Manage your passkeys for passwordless sign-in') }}</x-ui.subheading>

                <div class="settings-security__section-content" wire:cloak>
                    <div class="settings-security__passkey-list">
                        @forelse ($passkeys as $passkey)
                            <div class="settings-security__passkey {{ ! $loop->last ? 'settings-security__passkey--separated' : '' }}">
                                <div class="settings-security__passkey-identity">
                                    <div class="settings-security__passkey-icon">
                                        <x-ui.icon name="key" />
                                    </div>
                                    <div class="settings-security__passkey-details">
                                        <div class="settings-security__passkey-title">
                                            <p>{{ $passkey['name'] }}</p>
                                            @if ($passkey['authenticator'])
                                                <x-ui.badge>{{ $passkey['authenticator'] }}</x-ui.badge>
                                            @endif
                                        </div>
                                        <p class="settings-security__passkey-metadata">
                                            {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                            @if ($passkey['last_used_at_diff'])
                                                <span aria-hidden="true">/</span>
                                                {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <x-ui.button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="confirmDelete({{ $passkey['id'] }})"
                                    class="settings-security__remove-passkey"
                                    :aria-label="__('Remove passkey')"
                                >
                                    <x-ui.icon name="trash" />
                                </x-ui.button>
                            </div>
                        @empty
                            <div class="settings-security__empty">
                                <div class="settings-security__empty-icon">
                                    <x-ui.icon name="key" />
                                </div>
                                <p class="settings-security__empty-title">{{ __('No passkeys yet') }}</p>
                                <x-ui.text>{{ __('Add a passkey to sign in without a password') }}</x-ui.text>
                            </div>
                        @endforelse
                    </div>

                    <x-passkey-registration />
                </div>
            </section>
        @endif
    </x-pages::settings.layout>

    <x-ui.modal
        name="delete-passkey-modal"
        @close="$wire.closeDeleteModal()"
        :aria-label="__('Remove passkey')"
        wire:model="showDeleteModal"
    >
        <div class="settings-security__modal settings-security__modal-content">
            <div class="settings-security__modal-message">
                <x-ui.heading size="lg">{{ __('Remove passkey') }}</x-ui.heading>
                <x-ui.text>
                    {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                </x-ui.text>
            </div>

            <div class="settings-security__modal-actions">
                <x-ui.button variant="secondary" wire:click="closeDeleteModal">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button variant="danger" wire:click="deletePasskey">
                    {{ __('Remove passkey') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</section>
