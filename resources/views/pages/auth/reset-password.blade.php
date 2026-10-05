<x-layouts::auth :title="__('Reset password')">
    <div class="auth-page auth-page--reset-password">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <x-auth-session-status class="auth-page__status" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="auth-page__form">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <x-ui.input
                name="email"
                :value="request('email')"
                :label="__('Email')"
                type="email"
                required
                autocomplete="email"
            />

            <x-ui.password-input
                name="password"
                :label="__('Password')"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <x-ui.password-input
                name="password_confirmation"
                :label="__('Confirm password')"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <x-ui.button type="submit" variant="primary" class="auth-page__action" data-test="reset-password-button">
                {{ __('Reset password') }}
            </x-ui.button>
        </form>
    </div>
</x-layouts::auth>
