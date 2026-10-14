<?php

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Livewire\WorkPeriodComponent;
use App\Services\AbsenceService;
use App\Services\LeaveBalanceService;
use App\Services\TimeCalendarService;
use App\Services\TimeEntryService;
use App\Services\WorkTimeRuleService;
use App\Support\CalendarRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My time calendar')] class extends WorkPeriodComponent
{
    public string $month = '';

    public ?string $selectedDate = null;

    public string $selectedAction = '';

    public bool $selectingRange = false;

    public ?string $rangeStart = null;

    public ?string $rangeEnd = null;

    public string $rangeType = Absence::TYPE_VACATION;

    public bool $sickNoteSubmitted = false;

    public array $ruleWarnings = [];

    public bool $confirmRuleWarnings = false;

    public bool $calendarModalOpen = false;

    public function mount(): void
    {
        Gate::authorize('create', [TimeEntry::class, Auth::user()]);
        Gate::authorize('create', [Absence::class, Auth::user()]);
        $this->month = today()->format('Y-m');
    }

    #[Computed]
    public function calendar(): array
    {
        Gate::authorize('create', [TimeEntry::class, Auth::user()]);

        return app(TimeCalendarService::class)->for(Auth::user(), $this->month);
    }

    #[Computed]
    public function selectedDay(): ?array
    {
        if ($this->selectedDate === null) {
            return null;
        }

        foreach ($this->calendar['weeks'] as $week) {
            foreach ($week as $day) {
                if ($day['date'] === $this->selectedDate) {
                    return $day;
                }
            }
        }

        return null;
    }

    public function previousMonth(): void
    {
        $this->month = CalendarRange::clampMonth($this->validatedMonth()->subMonth())->format('Y-m');
        $this->resetSelection();
        unset($this->calendar);
    }

    public function nextMonth(): void
    {
        $this->month = CalendarRange::clampMonth($this->validatedMonth()->addMonth())->format('Y-m');
        $this->resetSelection();
        unset($this->calendar);
    }

    public function goToToday(): void
    {
        $this->month = today()->format('Y-m');
        $this->resetSelection();
        unset($this->calendar);
    }

    public function toggleRangeSelection(): void
    {
        $this->selectingRange = ! $this->selectingRange;
        $this->rangeStart = null;
        $this->rangeEnd = null;
    }

    public function resetRangeEnd(): void
    {
        $this->rangeStart = null;
        $this->rangeEnd = null;
        $this->selectedDate = null;
        $this->selectedAction = '';
    }

    public function selectDate(string $date): void
    {
        $this->validateDate($date);

        if ($this->selectingRange) {
            if ($this->rangeStart === null || $this->rangeEnd !== null) {
                $this->rangeStart = $date;
                $this->rangeEnd = null;
                $this->dispatch('toast-show', text: __('Select the last day of the absence.'));

                return;
            }

            $this->rangeEnd = $date;
            if ($this->rangeEnd < $this->rangeStart) {
                [$this->rangeStart, $this->rangeEnd] = [$this->rangeEnd, $this->rangeStart];
            }
            $this->selectedDate = $date;
            $this->selectedAction = 'range';
            $this->sickNoteSubmitted = false;
            $this->rangeType = Absence::TYPE_VACATION;
            $this->calendarModalOpen = true;
            return;
        }

        $this->selectedDate = $date;
        $this->selectedAction = '';
        $this->editingEntryId = null;
        $this->additionalPeriods = [];
        $this->ruleWarnings = [];
        $this->confirmRuleWarnings = false;
        $this->calendarModalOpen = true;
    }

    public function canLogTime(): bool
    {
        $day = $this->selectedDay;
        if ($day === null) {
            return false;
        }

        $date = CarbonImmutable::parse($day['date']);
        $earliest = today()->subDays((int) config('time_tracking.correction_window_days', 7));
        if ($date->lt($earliest) || $date->isFuture()) {
            return false;
        }

        return true;
    }

    public function canRequestVacation(): bool
    {
        $day = $this->selectedDay;
        if ($day === null || $day['date'] < today()->toDateString() || $day['absence_status'] !== null) {
            return false;
        }

        $date = CarbonImmutable::parse($day['date']);

        return app(LeaveBalanceService::class)->daysForRequest(Auth::user(), $date, $date) > 0;
    }

    public function canReportSickDay(): bool
    {
        return $this->selectedDay !== null && $this->selectedDay['absence_status'] === null;
    }

    public function beginAction(string $action): void
    {
        if ($this->selectedDay === null || ! in_array($action, ['time', 'vacation', 'sick'], true)) {
            abort(404);
        }

        if ($action === 'time' && ! $this->canLogTime()) {
            abort(403);
        }
        if ($action === 'vacation' && ! $this->canRequestVacation()) {
            abort(403);
        }

        $this->ruleWarnings = [];
        $this->confirmRuleWarnings = false;
        $this->additionalPeriods = [];
        $this->selectedAction = $action;

        if ($action === 'time' && $this->selectedDay['entry_id'] !== null
            && $this->selectedDay['entry_status'] !== TimeEntry::STATUS_IN_PROGRESS) {
            $entry = Auth::user()->timeEntries()->findOrFail($this->selectedDay['entry_id']);
            Gate::authorize('update', $entry);
            $this->editingEntryId = $entry->id;
            $this->startTime = substr((string) $entry->start_time, 0, 5);
            $this->endTime = substr((string) $entry->end_time, 0, 5);
            $this->breakMinutes = (string) $entry->break_minutes;
            $this->isSchoolDay = $entry->is_school_day;
        } elseif ($action === 'time') {
            $this->editingEntryId = null;
            $this->startTime = '08:00';
            $this->endTime = '16:30';
            $this->breakMinutes = '30';
            $this->isSchoolDay = false;
        } else {
            $this->sickNoteSubmitted = false;
        }
    }

    public function beginNewTime(): void
    {
        $this->beginAction('time');
        $this->editingEntryId = null;
        $this->startTime = '08:00';
        $this->endTime = '16:30';
        $this->breakMinutes = '30';
        $this->isSchoolDay = false;
    }

    public function editWorkPeriod(int $entryId): void
    {
        if (! $this->canLogTime()) {
            abort(403);
        }
        $entry = Auth::user()->timeEntries()->whereDate('date', $this->selectedDate)->findOrFail($entryId);
        Gate::authorize('update', $entry);
        if ($entry->status === TimeEntry::STATUS_IN_PROGRESS) {
            abort(403);
        }
        $this->beginAction('time');
        $this->editingEntryId = $entry->id;
        $this->startTime = substr((string) $entry->start_time, 0, 5);
        $this->endTime = substr((string) $entry->end_time, 0, 5);
        $this->breakMinutes = (string) $entry->break_minutes;
        $this->isSchoolDay = $entry->is_school_day;
    }

    public function submitTime(): void
    {
        $day = $this->selectedDay;
        if ($day === null || ! $this->canLogTime()) {
            abort(403);
        }

        $periods = $this->validatedWorkPeriods($day['date']);
        $entry = $this->editingEntryId === null ? null
            : Auth::user()->timeEntries()->whereDate('date', $day['date'])->findOrFail($this->editingEntryId);
        if ($entry !== null) {
            Gate::authorize('update', $entry);
        }
        $service = app(TimeEntryService::class);
        $service->validatePeriods(Auth::user(), $periods, $entry?->id);
        $this->ruleWarnings = app(WorkTimeRuleService::class)->evaluatePeriods(
            Auth::user(),
            $periods,
            $this->editingEntryId,
        );

        if ($this->ruleWarnings !== [] && ! $this->confirmRuleWarnings) {
            return;
        }

        $service->savePeriods(Auth::user(), $periods, $entry);

        $this->saved(__('Time entry saved.'));
    }

    public function confirmAndSubmitTime(): void
    {
        $this->confirmRuleWarnings = true;
        $this->submitTime();
    }

    public function submitAbsence(): void
    {
        $day = $this->selectedDay;
        if ($day === null || ! in_array($this->selectedAction, ['vacation', 'sick'], true)) {
            abort(403);
        }
        if ($this->selectedAction === 'vacation' && ! $this->canRequestVacation()) {
            abort(403);
        }

        $validated = $this->validate([
            'sickNoteSubmitted' => ['boolean'],
        ]);
        app(AbsenceService::class)->request(Auth::user(), [
            'type' => $this->selectedAction,
            'start_date' => $day['date'],
            'end_date' => $day['date'],
            'sick_note_submitted' => $validated['sickNoteSubmitted'],
        ]);

        $this->saved(__('Absence report submitted.'));
    }

    public function submitRangeAbsence(): void
    {
        if ($this->rangeStart === null || $this->rangeEnd === null) {
            abort(403);
        }

        $validated = $this->validate([
            'rangeStart' => ['required', 'date_format:Y-m-d'],
            'rangeEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:rangeStart'],
            'rangeType' => ['required', Rule::in([Absence::TYPE_SICK, Absence::TYPE_VACATION])],
            'sickNoteSubmitted' => ['boolean'],
        ]);
        $visibleDates = collect($this->calendar['weeks'])->flatten(1)->pluck('date');
        if (! $visibleDates->contains($validated['rangeStart'])
            || ! $visibleDates->contains($validated['rangeEnd'])) {
            abort(404);
        }
        if ($validated['rangeType'] === Absence::TYPE_VACATION && $validated['rangeStart'] < today()->toDateString()) {
            $this->addError('rangeType', __('Vacation requests from the calendar must start today or later.'));

            return;
        }

        app(AbsenceService::class)->request(Auth::user(), [
            'type' => $validated['rangeType'],
            'start_date' => $validated['rangeStart'],
            'end_date' => $validated['rangeEnd'],
            'sick_note_submitted' => $validated['sickNoteSubmitted'],
        ]);

        $this->saved(__('Absence report submitted.'));
    }

    public function updatedStartTime(): void
    {
        $this->clearRuleConfirmation();
    }

    public function updatedEndTime(): void
    {
        $this->clearRuleConfirmation();
    }

    public function updatedBreakMinutes(): void
    {
        $this->clearRuleConfirmation();
    }

    public function updatedIsSchoolDay(): void
    {
        $this->clearRuleConfirmation();
    }

    private function validateDate(string $date): void
    {
        validator(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $inCalendar = collect($this->calendar['weeks'])
            ->flatten(1)
            ->contains(fn (array $day): bool => $day['date'] === $date);

        if (! $inCalendar) {
            abort(404);
        }
    }

    protected function clearRuleConfirmation(): void
    {
        $this->ruleWarnings = [];
        $this->confirmRuleWarnings = false;
    }

    private function validatedMonth(): CarbonImmutable
    {
        $validated = validator(['month' => $this->month], [
            'month' => ['required', 'date_format:Y-m', ...CalendarRange::monthRules()],
        ])->validate();

        return CarbonImmutable::createFromFormat('!Y-m', $validated['month']);
    }

    private function saved(string $message): void
    {
        $this->calendarModalOpen = false;
        $this->dispatch('toast-show', text: $message);
        $this->resetSelection();
        unset($this->calendar);
    }

    private function resetSelection(): void
    {
        $this->selectedDate = null;
        $this->selectedAction = '';
        $this->selectingRange = false;
        $this->rangeStart = null;
        $this->rangeEnd = null;
        $this->editingEntryId = null;
        $this->additionalPeriods = [];
        $this->sickNoteSubmitted = false;
        $this->ruleWarnings = [];
        $this->confirmRuleWarnings = false;
    }
}; ?>

<div class="time-calendar">
    <header class="time-calendar__header">
        <div class="time-calendar__intro">
            <div class="app-page-title-row">
                <x-ui.heading level="1" size="xl">{{ __('My time calendar') }}</x-ui.heading>
                <x-app-page-actions />
            </div>
            <x-ui.text>{{ __('View and manage your working time and absences by month.') }}</x-ui.text>
        </div>
    </header>

    <section class="time-calendar__controls">
        <div class="time-calendar__month-navigation">
            <x-ui.button wire:click="previousMonth" aria-label="{{ __('Previous month') }}" :disabled="$month <= CalendarRange::minMonth()">‹</x-ui.button>
            <x-ui.button wire:click="goToToday">{{ __('Today') }}</x-ui.button>
            <x-ui.button wire:click="nextMonth" aria-label="{{ __('Next month') }}" :disabled="$month >= CalendarRange::maxMonth()">›</x-ui.button>
        </div>
        <x-ui.heading level="2" class="time-calendar__month-heading">{{ $this->calendar['month']->locale(app()->getLocale())->translatedFormat('F Y') }}</x-ui.heading>
        <x-ui.button variant="{{ $selectingRange ? 'primary' : 'secondary' }}" wire:click="toggleRangeSelection">
            {{ $selectingRange ? __('Cancel date range') : __('Select date range') }}
        </x-ui.button>
    </section>

    @if ($selectingRange)
        <p role="status" class="time-calendar__range-status">
            @if ($rangeStart === null)
                {{ __('Choose the first day of the absence.') }}
            @elseif ($rangeEnd === null)
                {{ __('Start: :date. Choose the last day of the absence.', ['date' => $rangeStart]) }}
            @else
                {{ __('Selected absence period: :start – :end.', ['start' => $rangeStart, 'end' => $rangeEnd]) }}
            @endif
        </p>
    @endif

    <section aria-label="{{ __('Monthly time calendar') }}" class="time-calendar__calendar-scroll">
        <div class="time-calendar__calendar">
            <div class="time-calendar__weekdays">
                @foreach ([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')] as $weekday)
                    <div class="time-calendar__weekday">
                        <abbr aria-label="{{ $weekday }}" title="{{ $weekday }}">
                            <span class="time-calendar__weekday-full">{{ $weekday }}</span>
                            <span class="time-calendar__weekday-short" aria-hidden="true">{{ mb_substr($weekday, 0, 2) }}</span>
                        </abbr>
                    </div>
                @endforeach
            </div>
            @foreach ($this->calendar['weeks'] as $week)
                <div class="time-calendar__week">
                    @foreach ($week as $day)
                        <button
                            type="button"
                            wire:key="calendar-day-{{ $day['date'] }}"
                            wire:click="selectDate('{{ $day['date'] }}')"
                            title="{{ $day['date'] }} · {{ $day['details'] }}"
                            aria-label="{{ $day['date'] }} · {{ $day['details'] }}"
                            @class([
                                'time-calendar__day',
                                'time-calendar__day--'.$day['status'],
                                'time-calendar__day--outside-month' => ! $day['is_current_month'],
                                'time-calendar__day--today' => $day['is_today'],
                                'time-calendar__day--range-endpoint' => $day['date'] === $rangeStart || $day['date'] === $rangeEnd,
                            ])
                        >
                            <span class="time-calendar__day-header">
                                <span class="time-calendar__day-number">{{ $day['day'] }}</span>
                                @if ($day['is_today'])
                                    <span class="time-calendar__today-label">{{ __('Today') }}</span>
                                @endif
                            </span>
                            <span class="time-calendar__day-details">{{ $day['details'] }}</span>
                            @if ($day['warnings'] !== [])
                                <span class="time-calendar__day-warning">⚠ {{ __('Rule warning') }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <section aria-label="{{ __('Calendar legend') }}" class="time-calendar__legend">
        @foreach ([
            ['holiday', __('NRW public holiday')],
            ['sick', __('Sick day')],
            ['vacation-pending', __('Pending vacation')],
            ['vacation', __('Approved vacation')],
            ['booked', __('Working time booked')],
            ['empty', __('Nothing entered')],
        ] as [$colorClass, $label])
            <x-ui.badge class="time-calendar__legend-item time-calendar__legend-item--{{ $colorClass }}">{{ $label }}</x-ui.badge>
        @endforeach
    </section>

    <x-ui.modal name="calendar-day-modal" :aria-label="__('Calendar day actions')" wire:model="calendarModalOpen" wire:ignore.self class="time-calendar__modal">
        @if ($this->selectedDay)
            <div class="time-calendar__modal-body">
                <div class="time-calendar__modal-heading">
                    <x-ui.heading size="lg">{{ $this->selectedDay['date'] }}</x-ui.heading>
                    <x-ui.text>{{ $this->selectedDay['details'] }}</x-ui.text>
                </div>

                @if ($selectedAction === '')
                    @if ($this->selectedDay['entries'] !== [])
                        <div class="time-calendar__modal-section">
                            <x-ui.heading level="2">{{ __('Work periods') }}</x-ui.heading>
                            @foreach ($this->selectedDay['entries'] as $period)
                                <x-ui.text>
                                    {{ $period['start'] }} – {{ $period['end'] ?? '—' }} ·
                                    {{ __('Break') }}: {{ $period['break_minutes'] }} min ·
                                    {{ number_format($period['net_minutes'] / 60, 2) }} h · {{ $period['status'] === TimeEntry::STATUS_IN_PROGRESS ? __('Clocked in') : __($period['status']) }}
                                </x-ui.text>
                            @endforeach
                        </div>
                    @endif
                    <div class="time-calendar__modal-section">
                        <x-ui.heading level="2">{{ __('What would you like to do?') }}</x-ui.heading>
                        @if ($this->canLogTime())
                            @foreach ($this->selectedDay['entries'] as $period)
                                @if ($period['status'] !== TimeEntry::STATUS_IN_PROGRESS)
                                    <x-ui.button class="time-calendar__action-button" wire:click="editWorkPeriod({{ $period['id'] }})">
                                        {{ __('Edit time entry') }}: {{ $period['start'] }} – {{ $period['end'] }}
                                    </x-ui.button>
                                @endif
                            @endforeach
                            <x-ui.button class="time-calendar__action-button" wire:click="beginNewTime">
                                {{ $this->selectedDay['entry_id'] ? __('Add another work period') : __('Log time') }}
                            </x-ui.button>
                        @endif
                        @if ($this->canRequestVacation())
                            <x-ui.button class="time-calendar__action-button" wire:click="beginAction('vacation')">{{ __('Request vacation') }}</x-ui.button>
                        @endif
                        @if ($this->canReportSickDay())
                            <x-ui.button class="time-calendar__action-button" wire:click="beginAction('sick')">{{ __('Report sick day') }}</x-ui.button>
                        @endif
                        <div class="time-calendar__modal-actions">
                            <x-ui.modal-close><x-ui.button>{{ __('Close') }}</x-ui.button></x-ui.modal-close>
                        </div>
                    </div>
                @elseif ($selectedAction === 'range')
                    <div class="time-calendar__modal-section">
                        <x-ui.heading level="2">{{ __('Request an absence for this period') }}</x-ui.heading>
                        <x-ui.text>{{ $rangeStart }} – {{ $rangeEnd }}</x-ui.text>
                        <x-ui.select wire:model="rangeType" :label="__('Absence type')">
                            <option value="vacation">{{ __('Vacation') }}</option>
                            <option value="sick">{{ __('Sick day') }}</option>
                        </x-ui.select>
                        @if ($rangeType === 'sick')
                            <x-ui.checkbox wire:model="sickNoteSubmitted" :label="__('Sick note (AU) submitted')" />
                            <x-ui.text>{{ __('Do not enter a diagnosis, reason, or medical document.') }}</x-ui.text>
                        @endif
                        @error('rangeType')
                            <p class="time-calendar__error" role="alert">{{ $message }}</p>
                        @enderror
                        @foreach (['rangeStart', 'rangeEnd', 'start_date', 'end_date'] as $field)
                            @error($field)<p class="time-calendar__error" role="alert">{{ $message }}</p>@enderror
                        @endforeach
                        <div class="time-calendar__modal-actions">
                            <x-ui.button type="button" wire:click="resetRangeEnd">{{ __('Choose dates again') }}</x-ui.button>
                            <x-ui.button variant="primary" wire:click="submitRangeAbsence">{{ __('Submit request') }}</x-ui.button>
                        </div>
                    </div>
                @elseif ($selectedAction === 'time')
                    <form wire:submit="submitTime" class="time-calendar__modal-section">
                        <x-ui.heading level="2">{{ $editingEntryId ? __('Edit time entry') : __('Log working time') }}</x-ui.heading>
                        <x-ui.work-period-fields :periods="$additionalPeriods" :allow-add="$editingEntryId === null" />
                        <x-ui.text>{{ __('Net time: :hours h', ['hours' => number_format($this->previewNetMinutes / 60, 2)]) }}</x-ui.text>
                        <x-ui.checkbox wire:model.live="isSchoolDay" :label="__('Vocational school day')" />
                        @foreach (['startTime', 'endTime', 'breakMinutes', 'isSchoolDay', 'additionalPeriods', 'date', 'start_time', 'end_time', 'break_minutes'] as $field)
                            @error($field)<p class="time-calendar__error" role="alert">{{ $message }}</p>@enderror
                        @endforeach
                        @if ($ruleWarnings !== [])
                            <div role="alert" class="time-calendar__warning">
                                <p class="time-calendar__warning-title">{{ __('Working-time rule warnings') }}</p>
                                <ul class="time-calendar__warning-list">
                                    @foreach ($ruleWarnings as $warning)
                                        <li><strong>{{ $warning['rule'] }}:</strong> {{ $warning['message'] }}</li>
                                    @endforeach
                                </ul>
                                <p>{{ __('These warnings do not prevent saving. Confirm to save anyway.') }}</p>
                            </div>
                        @endif
                        <div class="time-calendar__modal-actions">
                            <x-ui.button type="button" wire:click="$set('selectedAction', '')">{{ __('Back') }}</x-ui.button>
                            @if ($ruleWarnings !== [])
                                <x-ui.button type="button" variant="primary" wire:click="confirmAndSubmitTime">{{ __('Save anyway') }}</x-ui.button>
                            @else
                                <x-ui.button type="submit" variant="primary">{{ __('Save time entry') }}</x-ui.button>
                            @endif
                        </div>
                    </form>
                @else
                    <div class="time-calendar__modal-section">
                        <x-ui.heading level="2">{{ $selectedAction === 'sick' ? __('Report sick day') : __('Request vacation') }}</x-ui.heading>
                        @if ($selectedAction === 'sick')
                            <x-ui.checkbox wire:model="sickNoteSubmitted" :label="__('Sick note (AU) submitted')" />
                            <x-ui.text>{{ __('Do not enter a diagnosis, reason, or medical document.') }}</x-ui.text>
                        @endif
                        @foreach (['start_date', 'end_date', 'sick_note_submitted'] as $field)
                            @error($field)<p class="time-calendar__error" role="alert">{{ $message }}</p>@enderror
                        @endforeach
                        <div class="time-calendar__modal-actions">
                            <x-ui.button wire:click="$set('selectedAction', '')">{{ __('Back') }}</x-ui.button>
                            <x-ui.button variant="primary" wire:click="submitAbsence">{{ __('Submit request') }}</x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>
