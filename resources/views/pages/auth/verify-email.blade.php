<x-layouts::auth :title="__('Email verification')">
    <div class="auth-page auth-page--verify-email">
        <x-ui.text class="auth-page__message">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </x-ui.text>

        @if (session('status') == 'verification-link-sent')
            <x-ui.alert type="success" class="auth-page__status-message">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </x-ui.alert>
        @endif

        <div class="auth-page__actions">
            <form method="POST" action="{{ route('verification.send') }}" class="auth-page__form">
                @csrf
                <x-ui.button type="submit" variant="primary" class="auth-page__action">
                    {{ __('Resend verification email') }}
                </x-ui.button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="auth-page__form">
                @csrf
                <x-ui.button variant="ghost" type="submit" data-test="logout-button">
                    {{ __('Log out') }}
                </x-ui.button>
            </form>
        </div>
    </div>
</x-layouts::auth>
