<x-layouts::auth :title="__('Confirm password')">
    <div class="auth-page auth-page--confirm">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <x-auth-session-status class="auth-page__status" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('Confirm with passkey')"
            :loading-label="__('Confirming...')"
            :separator="__('Or confirm with password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="auth-page__form">
            @csrf

            <x-ui.password-input
                name="password"
                :label="__('Password')"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
            />

            <x-ui.button variant="primary" type="submit" class="auth-page__action" data-test="confirm-password-button">
                {{ __('Confirm') }}
            </x-ui.button>
        </form>
    </div>
</x-layouts::auth>
