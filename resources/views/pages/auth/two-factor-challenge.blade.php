<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="auth-page auth-page--two-factor">
        <div
            class="auth-page__two-factor-panel"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                focusOtp() {
                    this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
                },
                init() {
                    if (! this.showRecoveryInput) {
                        this.focusOtp();
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.focusOtp();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="auth-page__form">
                @csrf

                <div class="auth-page__two-factor-content">
                    <div x-show="!showRecoveryInput">
                        <div class="auth-page__otp" x-ref="otp">
                            <x-ui.otp
                                x-model="code"
                                length="6"
                                name="code"
                                label="OTP Code"
                            />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="auth-page__recovery-field">
                            <x-ui.input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                            />
                        </div>

                        @error('recovery_code')
                            <x-ui.alert type="error" class="auth-page__error">
                                {{ $message }}
                            </x-ui.alert>
                        @enderror
                    </div>

                    <x-ui.button variant="primary" type="submit" class="auth-page__action">
                        {{ __('Continue') }}
                    </x-ui.button>
                </div>

                <button class="auth-page__mode-toggle" type="button" @click="toggleInput()">
                    <span>{{ __('or you can') }}</span>
                    <span x-show="!showRecoveryInput">{{ __('login using a recovery code') }}</span>
                    <span x-show="showRecoveryInput">{{ __('login using an authentication code') }}</span>
                </button>
            </form>
        </div>
    </div>
</x-layouts::auth>
