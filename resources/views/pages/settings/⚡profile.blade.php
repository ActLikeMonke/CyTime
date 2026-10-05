<?php

use App\Models\WorkSchedule;
use App\Services\WorkScheduleService;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $weeklyHours = '40';
    public array $workDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
    public string $scheduleValidFrom = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->first_name = Auth::user()->first_name;
        $this->last_name = Auth::user()->last_name;
        $this->email = Auth::user()->email;

        $schedule = Auth::user()->workSchedules()
            ->whereDate('valid_from', '<=', today())
            ->orderByDesc('valid_from')
            ->first();

        if ($schedule instanceof WorkSchedule) {
            $this->weeklyHours = (string) $schedule->weekly_hours;
            $this->workDays = $schedule->work_days;
        }

        $this->scheduleValidFrom = today()->addDay()->toDateString();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast-show', type: 'success', text: __('Profile updated.'));
    }

    public function updateWorkingSchedule(): void
    {
        $user = Auth::user();

        Gate::authorize('updateSchedule', $user);

        app(WorkScheduleService::class)->change(
            $user,
            $user,
            $this->weeklyHours,
            $this->workDays,
            $this->scheduleValidFrom,
        );

        $this->dispatch('toast-show', type: 'success', text: __('Working schedule updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="settings-profile">
    @include('partials.settings-heading')

    <x-ui.heading level="2" class="visually-hidden">{{ __('Profile settings') }}</x-ui.heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="settings-profile__form">
            <x-ui.input wire:model="first_name" error-key="first_name" :label="__('First name')" type="text" required autofocus autocomplete="given-name" />
            <x-ui.input wire:model="last_name" error-key="last_name" :label="__('Last name')" type="text" required autocomplete="family-name" />

            <div class="settings-profile__email-field">
                <x-ui.input wire:model="email" error-key="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div class="settings-profile__verification">
                        <x-ui.text>
                            {{ __('Your email address is unverified.') }}

                            <x-ui.link wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </x-ui.link>
                        </x-ui.text>

                        @if (session('status') === 'verification-link-sent')
                            <x-ui.alert type="success" class="settings-profile__status">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </x-ui.alert>
                        @endif
                    </div>
                @endif
            </div>

            <div class="settings-profile__actions">
                <x-ui.button variant="primary" type="submit" data-test="update-profile-button">{{ __('Save') }}</x-ui.button>
            </div>
        </form>

        <form wire:submit="updateWorkingSchedule" class="settings-profile__schedule-form">
            <div class="settings-profile__schedule-heading">
                <x-ui.heading level="3">{{ __('Working schedule') }}</x-ui.heading>
                <x-ui.text>{{ __('Schedule changes are recorded as history and apply from the selected date. Leave entitlement is not changed automatically.') }}</x-ui.text>
            </div>

            <x-ui.input
                wire:model="weeklyHours"
                error-key="weeklyHours"
                :label="__('Weekly working hours')"
                type="number"
                min="0.01"
                max="40"
                step="0.01"
                required
            />
            @error('weeklyHours')
                <p class="settings-profile__error" role="alert">{{ $message }}</p>
            @enderror

            <x-ui.input
                wire:model="scheduleValidFrom"
                :error="$errors->first('scheduleValidFrom') ?: $errors->first('validFrom')"
                :label="__('Effective from')"
                type="date"
                required
            />

            <fieldset class="settings-profile__workdays">
                <legend class="settings-profile__legend">{{ __('Workdays') }}</legend>
                @foreach (['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday')] as $day => $label)
                    <x-ui.checkbox class="settings-profile__workday" wire:model="workDays" value="{{ $day }}" :label="$label" />
                @endforeach
                @error('workDays')
                    <p class="settings-profile__error" role="alert">{{ $message }}</p>
                @enderror
                @error('workDays.*')
                    <p class="settings-profile__error" role="alert">{{ $message }}</p>
                @enderror
                @error('validFrom')
                    <p class="settings-profile__error" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-ui.button variant="primary" type="submit">{{ __('Save schedule') }}</x-ui.button>
        </form>

        <section class="settings-profile__schedule-form" aria-labelledby="data-export-heading">
            <div class="settings-profile__schedule-heading">
                <x-ui.heading level="3" id="data-export-heading">{{ __('Download your data') }}</x-ui.heading>
                <x-ui.text>{{ __('Download a copy of your profile, work schedules, time entries, absences, and related audit history.') }}</x-ui.text>
            </div>
            <div class="cluster">
                <x-ui.button variant="secondary" href="{{ route('settings.data-export', ['format' => 'json']) }}">{{ __('Download JSON') }}</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('settings.data-export', ['format' => 'csv']) }}">{{ __('Download CSV') }}</x-ui.button>
            </div>
        </section>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
