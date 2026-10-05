<x-layouts::auth :title="__('Register')">
    <div class="auth-page auth-page--register">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <x-auth-session-status class="auth-page__status" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="auth-page__form">
            @csrf
            <x-ui.input
                name="first_name"
                :label="__('First name')"
                :value="old('first_name')"
                type="text"
                required
                autofocus
                autocomplete="given-name"
                :placeholder="__('First name')"
            />

            <x-ui.input
                name="last_name"
                :label="__('Last name')"
                :value="old('last_name')"
                type="text"
                required
                autocomplete="family-name"
                :placeholder="__('Last name')"
            />

            <x-ui.input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <x-ui.input
                name="birth_date"
                :label="__('Date of birth')"
                :value="old('birth_date')"
                type="date"
                required
                autocomplete="bday"
                max="{{ today()->toDateString() }}"
            />

            <x-ui.input
                name="weekly_hours"
                :label="__('Weekly working hours')"
                :value="old('weekly_hours', 40)"
                type="number"
                min="0.01"
                max="40"
                step="0.01"
                required
            />

            <fieldset class="auth-page__workdays">
                <legend class="auth-page__legend">
                    {{ __('Workdays') }}
                </legend>
                @php($defaultWorkDays = ['mon', 'tue', 'wed', 'thu', 'fri'])
                @foreach (['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday')] as $day => $label)
                    <x-ui.checkbox
                        class="auth-page__workday"
                        name="work_days[]"
                        value="{{ $day }}"
                        :label="$label"
                        :checked="in_array($day, old('work_days', $defaultWorkDays), true)"
                    />
                @endforeach
                @error('work_days')
                    <p class="auth-page__error" role="alert">{{ $message }}</p>
                @enderror
                @error('work_days.*')
                    <p class="auth-page__error" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-ui.password-input
                name="password"
                :label="__('Password')"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <section class="auth-page__privacy-notice" aria-labelledby="privacy-notice-title">
                <h2 id="privacy-notice-title" class="auth-page__notice-title">
                    {{ __('Privacy notice (Article 13 GDPR)') }}
                </h2>
                <p>
                    {{ __('CyTime uses your name, email address, date of birth and working schedule to manage your account and official working-time records. Your records are available to you and authorized supervisors. They are retained only as required for employment and working-time record obligations.') }}
                </p>
                <p>
                    {{ __('For the controller’s contact details, retention periods, information about your rights, or to make a privacy request, contact your employer’s HR or data protection contact. You may also contact your competent data protection supervisory authority.') }}
                </p>
                <x-ui.checkbox
                    name="privacy_notice_acknowledged"
                    value="1"
                    :label="__('I have read and acknowledge this privacy notice.')"
                    :checked="old('privacy_notice_acknowledged')"
                    required
                />
            </section>

            <x-ui.password-input
                name="password_confirmation"
                :label="__('Confirm password')"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <x-ui.button type="submit" variant="primary" class="auth-page__action" data-test="register-user-button">
                {{ __('Create account') }}
            </x-ui.button>
        </form>

        <div class="auth-page__footer">
            <span>{{ __('Already have an account?') }}</span>
            <x-ui.link :href="route('login')" wire:navigate>{{ __('Log in') }}</x-ui.link>
        </div>
    </div>
</x-layouts::auth>
