<x-layouts::auth :title="__('Forgot password')">
    <div class="auth-page auth-page--forgot-password">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <x-auth-session-status class="auth-page__status" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="auth-page__form">
            @csrf

            <x-ui.input
                name="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <x-ui.button variant="primary" type="submit" class="auth-page__action" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </x-ui.button>
        </form>

        <div class="auth-page__footer">
            <span>{{ __('Or, return to') }}</span>
            <x-ui.link :href="route('login')" wire:navigate>{{ __('log in') }}</x-ui.link>
        </div>
    </div>
</x-layouts::auth>
