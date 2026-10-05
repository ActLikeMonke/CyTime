<x-layouts::auth :title="__('Log in')">
    <div class="auth-page auth-page--login">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <x-auth-session-status class="auth-page__status" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="auth-page__form">
            @csrf

            <x-ui.input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <div class="auth-page__password-field">
                <x-ui.password-input
                    name="password"
                    :label="__('Password')"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                />

                @if (Route::has('password.request'))
                    <x-ui.link class="auth-page__password-link" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </x-ui.link>
                @endif
            </div>

            <x-ui.checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <x-ui.button variant="primary" type="submit" class="auth-page__action" data-test="login-button">
                {{ __('Log in') }}
            </x-ui.button>
        </form>

        <div class="auth-page__footer">
            <span>{{ __('Don\'t have an account?') }}</span>
            <x-ui.link :href="route('register')" wire:navigate>{{ __('Sign up') }}</x-ui.link>
        </div>
    </div>
</x-layouts::auth>
