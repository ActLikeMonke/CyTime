<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Services\AccountDeactivationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deactivateAccount(Logout $logout, AccountDeactivationService $accounts): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $accounts->deactivate($user);
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<x-ui.modal
    name="confirm-user-deletion"
    aria-labelledby="delete-account-title"
    :initially-open="$errors->has('password') || $errors->has('account')"
>
    <div class="settings-delete-account__modal">
        <form method="POST" wire:submit="deleteUser" class="settings-delete-account__form">
            <div class="settings-delete-account__message">
                <x-ui.heading id="delete-account-title" size="lg">{{ __('Are you sure you want to deactivate your account?') }}</x-ui.heading>

                <x-ui.subheading>
                    {{ __('You will be signed out and can no longer use this account. Official time records will be retained, and personal account data will be anonymized after the retention period. Please enter your password to confirm.') }}
                </x-ui.subheading>
            </div>

            @error('account')
                <x-ui.alert type="error" role="alert">{{ $message }}</x-ui.alert>
            @enderror

            <x-ui.password-input wire:model="password" error-key="password" :label="__('Password')" />

            <div class="settings-delete-account__actions">
                <x-ui.modal-close>
                    <x-ui.button variant="secondary">{{ __('Cancel') }}</x-ui.button>
                </x-ui.modal-close>

                <x-ui.button variant="danger" type="submit" data-test="confirm-delete-user-button">
                    {{ __('Deactivate account') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-ui.modal>
