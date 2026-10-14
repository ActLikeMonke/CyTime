<?php

use App\Models\TimeEntry;
use App\Livewire\WorkPeriodComponent;
use App\Services\TimeEntryService;
use App\Services\TimeOverviewService;
use App\Services\WeeklyBalanceService;
use App\Services\WorkTimeRuleService;
use App\Support\CalendarRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My time')] class extends WorkPeriodComponent {
    public string $period = 'week';
    public string $referenceDate = '';
    public string $entryDate = '';
    public string $clockBreakMinutes = '0';
    public array $ruleWarnings = [];
    public bool $confirmRuleWarnings = false;
    public array $clockOutWarnings = [];
    public bool $confirmClockOutWarnings = false;

    public function mount(): void
    {
        $this->referenceDate = today()->toDateString();
        $this->entryDate = today()->toDateString();
    }

    public function updatedPeriod(string $period): void
    {
        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $this->period = 'week';
        }

        unset($this->overview);
    }

    public function updatedReferenceDate(): void
    {
        unset($this->overview, $this->weeklyBalance);
    }

    public function updatedEntryDate(): void
    {
        $this->clearRuleConfirmation();
    }

    public function previousEntryDate(): void
    {
        $this->entryDate = CarbonImmutable::parse($this->entryDate)->subDay()->toDateString();
        $this->clearRuleConfirmation();
    }

    public function nextEntryDate(): void
    {
        $nextDate = CarbonImmutable::parse($this->entryDate)->addDay();

        if ($nextDate->lessThanOrEqualTo(CarbonImmutable::today())) {
            $this->entryDate = $nextDate->toDateString();
            $this->clearRuleConfirmation();
        }
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

    #[Computed]
    public function overview(): array
    {
        return app(TimeOverviewService::class)->for(Auth::user(), $this->period, $this->referenceDate);
    }

    #[Computed]
    public function weeklyBalance(): array
    {
        return app(WeeklyBalanceService::class)->for(Auth::user(), CarbonImmutable::today());
    }

    #[Computed]
    public function overallBalance(): int
    {
        return app(WeeklyBalanceService::class)->cumulativeBalance(Auth::user(), CarbonImmutable::today());
    }

    #[Computed]
    public function openEntry(): ?TimeEntry
    {
        return Auth::user()->timeEntries()
            ->whereDate('date', today())
            ->where('status', TimeEntry::STATUS_IN_PROGRESS)
            ->first();
    }

    public function previousPeriod(): void
    {
        $date = CarbonImmutable::parse($this->referenceDate);
        $previous = match ($this->period) {
            'day' => $date->subDay(),
            'month' => $date->subMonth(),
            default => $date->subWeek(),
        };
        $this->referenceDate = max($previous->toDateString(), CalendarRange::minDateString());
        unset($this->overview);
    }

    public function nextPeriod(): void
    {
        $date = CarbonImmutable::parse($this->referenceDate);
        $next = match ($this->period) {
            'day' => $date->addDay(),
            'month' => $date->addMonth(),
            default => $date->addWeek(),
        };

        if (! $next->isFuture()) {
            $this->referenceDate = $next->toDateString();
            unset($this->overview);
        }
    }

    public function saveEntry(): void
    {
        $actor = Auth::user();
        $validated = $this->validate([
            'entryDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.CalendarRange::minDateString()],
        ]);
        $service = app(TimeEntryService::class);
        $periods = $this->validatedWorkPeriods($validated['entryDate']);
        $entry = $this->editingEntryId === null ? null : $actor->timeEntries()->findOrFail($this->editingEntryId);
        if ($entry !== null) {
            Gate::authorize('update', $entry);
        }
        $service->validatePeriods($actor, $periods, $entry?->id);
        $this->ruleWarnings = app(WorkTimeRuleService::class)->evaluatePeriods(
            $actor,
            $periods,
            $this->editingEntryId,
        );

        if ($this->ruleWarnings !== [] && ! $this->confirmRuleWarnings) {
            return;
        }

        $service->savePeriods($actor, $periods, $entry);

        $this->editingEntryId = null;
        $this->ruleWarnings = [];
        $this->confirmRuleWarnings = false;
        unset($this->overview, $this->weeklyBalance, $this->overallBalance);
        $this->dispatch('toast-show', text: __('Time entry saved.'));
    }

    public function confirmAndSaveEntry(): void
    {
        $this->confirmRuleWarnings = true;
        $this->saveEntry();
    }

    /**
     * @return array<int, array<int, array{code: string, rule: string, message: string}>>
     */
    #[Computed]
    public function overviewRuleWarnings(): array
    {
        $service = app(WorkTimeRuleService::class);
        $warnings = [];

        foreach ($this->overview['entries'] as $entry) {
            if ($entry->end_time === null) {
                continue;
            }

            $entryWarnings = $service->evaluate($entry->user, [
                'date' => $entry->date->toDateString(),
                'start_time' => (string) $entry->start_time,
                'end_time' => (string) $entry->end_time,
                'break_minutes' => $entry->break_minutes,
                'is_school_day' => $entry->is_school_day,
            ], $entry->id);
            if ($entryWarnings !== []) {
                $warnings[$entry->id] = $entryWarnings;
            }
        }

        return $warnings;
    }

    public function editEntry(int $entryId): void
    {
        $entry = Auth::user()->timeEntries()->findOrFail($entryId);
        Gate::authorize('update', $entry);

        if ($entry->status === TimeEntry::STATUS_IN_PROGRESS) {
            $this->addError('entry', __('Clock out before editing this entry.'));

            return;
        }

        $this->clearRuleConfirmation();
        $this->additionalPeriods = [];
        $this->editingEntryId = $entry->id;
        $this->entryDate = $entry->date->toDateString();
        $this->startTime = substr((string) $entry->start_time, 0, 5);
        $this->endTime = substr((string) $entry->end_time, 0, 5);
        $this->breakMinutes = (string) $entry->break_minutes;
        $this->isSchoolDay = $entry->is_school_day;
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = Auth::user()->timeEntries()->findOrFail($entryId);
        app(TimeEntryService::class)->delete(Auth::user(), $entry);

        unset($this->openEntry, $this->overview, $this->weeklyBalance, $this->overallBalance);
        $this->dispatch('toast-show', text: __('Time entry deleted.'));
    }

    public function clockIn(): void
    {
        app(TimeEntryService::class)->clockIn(Auth::user());
        unset($this->openEntry, $this->overview);
        $this->dispatch('toast-show', text: __('Clocked in.'));
    }

    public function clockOut(): void
    {
        $entry = $this->openEntry;

        if ($entry === null) {
            $this->addError('clock', __('There is no open time entry to clock out.'));

            return;
        }

        $validated = $this->validate([
            'clockBreakMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);
        $endTime = now()->format('H:i:s');
        $data = [
            'date' => $entry->date->toDateString(),
            'start_time' => substr((string) $entry->start_time, 0, 5),
            'end_time' => $endTime,
            'break_minutes' => (int) $validated['clockBreakMinutes'],
            'is_school_day' => $entry->is_school_day,
        ];
        $this->clockOutWarnings = app(WorkTimeRuleService::class)->evaluate(
            Auth::user(),
            $data,
            $entry->id,
        );

        if ($this->clockOutWarnings !== [] && ! $this->confirmClockOutWarnings) {
            return;
        }

        app(TimeEntryService::class)->clockOut(Auth::user(), $entry, (int) $validated['clockBreakMinutes']);
        $this->clockBreakMinutes = '0';
        $this->clockOutWarnings = [];
        $this->confirmClockOutWarnings = false;
        unset($this->openEntry, $this->overview, $this->weeklyBalance, $this->overallBalance);
        $this->dispatch('toast-show', text: __('Clocked out.'));
    }

    public function confirmAndClockOut(): void
    {
        $this->confirmClockOutWarnings = true;
        $this->clockOut();
    }

    public function resetEntryForm(): void
    {
        $this->editingEntryId = null;
        $this->additionalPeriods = [];
        $this->clearRuleConfirmation();
    }

    protected function clearRuleConfirmation(): void
    {
        $this->ruleWarnings = [];
        $this->confirmRuleWarnings = false;
    }

    public function updatedClockBreakMinutes(): void
    {
        $this->clockOutWarnings = [];
        $this->confirmClockOutWarnings = false;
    }

}; ?>

<div class="time-page">
    <header class="time-page__header">
        <div class="time-page__title-row">
            <x-ui.heading level="1" size="xl">{{ __('My time') }}</x-ui.heading>
            <x-app-page-actions />
        </div>
        <x-ui.text>{{ __('Record your working time and review your hours and absences.') }}</x-ui.text>
    </header>

    <section class="time-page__balance" aria-label="{{ __('Weekly balance') }}">
        <div class="time-page__balance-card">
            <x-ui.text>{{ __('Weekly target') }}</x-ui.text>
            <x-ui.heading level="2">{{ number_format($this->weeklyBalance['target_minutes'] / 60, 2) }} h</x-ui.heading>
        </div>
        <div class="time-page__balance-card">
            <x-ui.text>{{ __('Worked this week') }}</x-ui.text>
            <x-ui.heading level="2">{{ number_format($this->weeklyBalance['worked_minutes'] / 60, 2) }} h</x-ui.heading>
        </div>
        <div class="time-page__balance-card">
            <x-ui.text>{{ __('Hours behind this week') }}</x-ui.text>
            <x-ui.heading level="2">{{ number_format($this->weeklyBalance['remaining_minutes'] / 60, 2) }} h</x-ui.heading>
            <x-ui.text>{{ __('Overtime / undertime') }}: {{ number_format($this->weeklyBalance['balance_minutes'] / 60, 2) }} h</x-ui.text>
        </div>
        <div class="time-page__balance-card">
            <x-ui.text>{{ __('Overall balance since first schedule') }}</x-ui.text>
            <x-ui.heading level="2">{{ number_format($this->overallBalance / 60, 2) }} h</x-ui.heading>
        </div>
    </section>

    <section class="time-page__overview">
        <div class="time-page__overview-header">
            <x-ui.heading level="2">{{ __('Hours and absences') }}</x-ui.heading>
            <div class="time-page__period-controls">
                <x-ui.select wire:model.live="period" :label="__('View')">
                    <option value="day">{{ __('Day') }}</option>
                    <option value="week">{{ __('Week') }}</option>
                    <option value="month">{{ __('Month') }}</option>
                </x-ui.select>
                <x-ui.button wire:click="previousPeriod" aria-label="{{ __('Previous period') }}" :disabled="$referenceDate <= CalendarRange::minDateString()">‹</x-ui.button>
                <x-ui.input wire:model.live.debounce.1000ms="referenceDate" :label="__('Date')" type="date" :max="today()->toDateString()" />
                <x-ui.button wire:click="nextPeriod" aria-label="{{ __('Next period') }}" :disabled="CarbonImmutable::parse($referenceDate)->isToday()">›</x-ui.button>
            </div>
        </div>

        <x-ui.text class="time-page__summary">
            {{ $this->overview['start']->toDateString() }} – {{ $this->overview['end']->toDateString() }} ·
            {{ __('Total worked') }}: {{ number_format($this->overview['total_minutes'] / 60, 2) }} h
        </x-ui.text>

        <div class="time-page__table-scroll">
            <table class="time-page__table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Break') }}</th>
                        <th>{{ __('Net') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->overview['entries'] as $entry)
                        <tr wire:key="time-entry-{{ $entry->id }}">
                            <td>{{ $entry->date->toDateString() }}</td>
                            <td>{{ substr((string) $entry->start_time, 0, 5) }} – {{ $entry->end_time ? substr((string) $entry->end_time, 0, 5) : '—' }}</td>
                            <td>{{ $entry->break_minutes }} min</td>
                            <td>{{ number_format($entry->netMinutes() / 60, 2) }} h</td>
                            <td>{{ $entry->status === TimeEntry::STATUS_IN_PROGRESS ? __('Clocked in') : __($entry->status) }}{{ $entry->is_school_day ? ' · '.__('School') : '' }}</td>
                            <td>
                                @if (isset($this->overviewRuleWarnings[$entry->id]))
                                    <details class="time-page__rule-details">
                                        <summary>{{ __('⚠ Rule warning') }}</summary>
                                        <ul>
                                            @foreach ($this->overviewRuleWarnings[$entry->id] as $warning)
                                                <li><strong>{{ $warning['rule'] }}:</strong> {{ $warning['message'] }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                                @if ($entry->status !== TimeEntry::STATUS_IN_PROGRESS)
                                    <x-ui.button size="sm" wire:click="editEntry({{ $entry->id }})">{{ __('Edit') }}</x-ui.button>
                                @endif
                                <x-ui.button size="sm" variant="danger" wire:click="deleteEntry({{ $entry->id }})">{{ __('Delete') }}</x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="time-page__empty-state">{{ __('No time entries for this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="time-page__absence-list">
            <x-ui.heading level="3">{{ __('Absences') }}</x-ui.heading>
            @forelse ($this->overview['absences'] as $absence)
                <p>
                    {{ ucfirst($absence->type) }} ·
                    {{ \Illuminate\Support\Carbon::parse($absence->start_date)->toDateString() }} –
                    {{ \Illuminate\Support\Carbon::parse($absence->end_date)->toDateString() }} ·
                    {{ __($absence->status) }}
                </p>
            @empty
                <x-ui.text>{{ __('No absences for this period.') }}</x-ui.text>
            @endforelse
        </div>
    </section>

    <section class="time-page__work-area">
        <section class="time-page__panel">
            <x-ui.heading level="2">{{ __('Clock in / clock out') }}</x-ui.heading>
            @if ($this->openEntry)
                <x-ui.text>{{ __('Clocked in at :time', ['time' => substr((string) $this->openEntry->start_time, 0, 5)]) }}</x-ui.text>
                <x-ui.input wire:model="clockBreakMinutes" :label="__('Break (minutes)')" type="number" min="0" max="1440" required />
                @if ($clockOutWarnings !== [])
                    <div role="alert" class="time-page__warning">
                        <p class="time-page__warning-title">{{ __('Working-time rule warnings') }}</p>
                        <ul class="time-page__warning-list">
                            @foreach ($clockOutWarnings as $warning)
                                <li><strong>{{ $warning['rule'] }}:</strong> {{ $warning['message'] }}</li>
                            @endforeach
                        </ul>
                        <p>{{ __('These warnings do not prevent saving. Confirm to clock out anyway.') }}</p>
                        <x-ui.button variant="primary" wire:click="confirmAndClockOut">{{ __('Clock out anyway') }}</x-ui.button>
                    </div>
                @else
                    <x-ui.button variant="primary" wire:click="clockOut">{{ __('Clock out') }}</x-ui.button>
                @endif
                @error('clockBreakMinutes')
                    <p class="time-page__error" role="alert">{{ $message }}</p>
                @enderror
                @error('breakMinutes')
                    <p class="time-page__error" role="alert">{{ $message }}</p>
                @enderror
            @else
                <x-ui.button variant="primary" wire:click="clockIn">{{ __('Clock in') }}</x-ui.button>
            @endif
            @foreach (['clock', 'start_time', 'timeEntry'] as $field)
                @error($field)<p class="time-page__error" role="alert">{{ $message }}</p>@enderror
            @endforeach
        </section>

        <form wire:submit="saveEntry" class="time-page__entry-form">
            <x-ui.heading level="2">{{ $editingEntryId ? __('Correct time entry') : __('Log working time') }}</x-ui.heading>
            <div class="time-page__entry-date">
                <x-ui.input wire:model.live.debounce.1000ms="entryDate" :label="__('Date')" type="date" :max="today()->toDateString()" required />
                <x-ui.button type="button" size="sm" variant="secondary" wire:click="previousEntryDate" aria-label="{{ __('Previous day') }}" title="{{ __('Previous day') }}">‹</x-ui.button>
                <x-ui.button type="button" size="sm" variant="secondary" wire:click="nextEntryDate" :disabled="CarbonImmutable::parse($entryDate)->isToday()" aria-label="{{ __('Next day') }}" title="{{ __('Next day') }}">›</x-ui.button>
            </div>
            <x-ui.work-period-fields :periods="$additionalPeriods" :allow-add="$editingEntryId === null" />
            <x-ui.checkbox wire:model.live="isSchoolDay" :label="__('Vocational school day')" />
            <x-ui.text>{{ __('Net time: :hours h', ['hours' => number_format($this->previewNetMinutes / 60, 2)]) }}</x-ui.text>
            @foreach (['entryDate', 'startTime', 'endTime', 'breakMinutes', 'isSchoolDay', 'additionalPeriods', 'date', 'start_time', 'end_time', 'break_minutes', 'is_school_day', 'entry'] as $field)
                @error($field)
                    <p class="time-page__error" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
            @if ($ruleWarnings !== [])
                <div role="alert" class="time-page__warning">
                    <p class="time-page__warning-title">{{ __('Working-time rule warnings') }}</p>
                    <ul class="time-page__warning-list">
                        @foreach ($ruleWarnings as $warning)
                            <li><strong>{{ $warning['rule'] }}:</strong> {{ $warning['message'] }}</li>
                        @endforeach
                    </ul>
                    <p>{{ __('These warnings do not prevent saving. Confirm to save this entry anyway.') }}</p>
                </div>
            @endif
            <div class="time-page__form-actions">
                @if ($ruleWarnings !== [])
                    <x-ui.button type="button" variant="primary" wire:click="confirmAndSaveEntry">{{ __('Save anyway') }}</x-ui.button>
                @else
                    <x-ui.button type="submit" variant="primary">{{ $editingEntryId ? __('Save correction') : __('Save time entry') }}</x-ui.button>
                @endif
                @if ($editingEntryId)
                    <x-ui.button type="button" wire:click="resetEntryForm">{{ __('Cancel') }}</x-ui.button>
                @endif
            </div>
        </form>

    </section>
</div>
