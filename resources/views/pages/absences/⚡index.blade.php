<?php

use App\Models\Absence;
use App\Services\AbsenceService;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Absences and leave')] class extends Component
{
    public string $type = 'vacation';

    public string $startDate = '';

    public string $endDate = '';

    public bool $sickNoteSubmitted = false;

    public function mount(): void
    {
        $this->startDate = today()->toDateString();
        $this->endDate = today()->toDateString();
    }

    #[Computed]
    public function balance(): array
    {
        return app(LeaveBalanceService::class)->for(Auth::user(), (int) today()->format('Y'));
    }

    #[Computed]
    public function requests()
    {
        return Auth::user()->absences()->orderByDesc('start_date')->get();
    }

    #[Computed]
    public function requestedVacationDays(): int
    {
        if ($this->type !== Absence::TYPE_VACATION
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startDate)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->endDate)
            || $this->startDate > $this->endDate) {
            return 0;
        }

        return app(LeaveBalanceService::class)->daysForRequest(
            Auth::user(),
            CarbonImmutable::parse($this->startDate),
            CarbonImmutable::parse($this->endDate),
        );
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'type' => ['required', 'in:sick,vacation'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'sickNoteSubmitted' => ['boolean'],
        ]);
        app(AbsenceService::class)->request(Auth::user(), [
            'type' => $validated['type'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'sick_note_submitted' => $validated['sickNoteSubmitted'],
        ]);

        $this->reset('sickNoteSubmitted');
        $this->dispatch('toast-show', text: __('Absence report submitted.'));
        unset($this->requests, $this->balance);
    }

    public function cancel(int $absenceId): void
    {
        $absence = Auth::user()->absences()->findOrFail($absenceId);
        app(AbsenceService::class)->cancel(Auth::user(), $absence);

        unset($this->requests, $this->balance);
        $this->dispatch('toast-show', text: __('Pending request cancelled.'));
    }
}; ?>

<div class="absence-page">
    <header class="absence-page__header">
        <div class="app-page-title-row">
            <x-ui.heading level="1" size="xl">{{ __('Absences and leave') }}</x-ui.heading>
            <x-app-page-actions />
        </div>
        <x-ui.text>{{ __('Report sick days or request vacation. Sick reports count immediately; vacation counts after approval.') }}</x-ui.text>
    </header>

    <section class="absence-page__balance" aria-label="{{ __('Leave balance') }}">
        <div class="absence-page__balance-card">
            <x-ui.text>{{ __('Annual entitlement') }}</x-ui.text>
            <x-ui.heading level="2">{{ $this->balance['entitlement'] }} {{ __('days') }}</x-ui.heading>
        </div>
        <div class="absence-page__balance-card">
            <x-ui.text>{{ __('Carry-over') }}</x-ui.text>
            <x-ui.heading level="2">{{ $this->balance['carry_over'] }} {{ __('days') }}</x-ui.heading>
        </div>
        <div class="absence-page__balance-card">
            <x-ui.text>{{ __('Used this year') }}</x-ui.text>
            <x-ui.heading level="2">{{ $this->balance['used'] }} {{ __('days') }}</x-ui.heading>
        </div>
        <div class="absence-page__balance-card">
            <x-ui.text>{{ __('Remaining leave') }}</x-ui.text>
            <x-ui.heading level="2">{{ $this->balance['remaining'] }} {{ __('days') }}</x-ui.heading>
            <x-ui.text>{{ __(':count pending days', ['count' => $this->balance['pending']]) }}</x-ui.text>
        </div>
    </section>

    <section class="absence-page__form-panel">
        <x-ui.heading level="2">{{ __('Report an absence') }}</x-ui.heading>
        <form wire:submit="submit" class="absence-page__form">
            <x-ui.select wire:model.live="type" :label="__('Type')" required>
                <option value="sick">{{ __('Sick day') }}</option>
                <option value="vacation">{{ __('Vacation') }}</option>
            </x-ui.select>
            <x-ui.input wire:model.live="startDate" :label="__('From')" type="date" required />
            <x-ui.input wire:model.live="endDate" :label="__('Until')" type="date" required />
            @if ($type === 'sick')
                <x-ui.checkbox wire:model="sickNoteSubmitted" :label="__('Sick note (AU) submitted')" />
                <x-ui.text>{{ __('Do not enter a diagnosis, reason, or medical document.') }}</x-ui.text>
            @else
                <x-ui.text>{{ __('Scheduled workdays excluding NRW public holidays: :count', ['count' => $this->requestedVacationDays]) }}</x-ui.text>
                @if ($this->requestedVacationDays + $this->balance['pending'] > $this->balance['remaining'])
                    <p role="status" class="absence-page__balance-warning">
                        {{ __('This request together with pending requests is above your remaining leave balance. You can still submit it for supervisor review.') }}
                    </p>
                @endif
            @endif

            @foreach (['type', 'startDate', 'endDate', 'sickNoteSubmitted'] as $field)
                @error($field)
                    <p class="absence-page__error" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
            @foreach (['start_date', 'end_date', 'sick_note_submitted'] as $field)
                @error($field)
                    <p class="absence-page__error" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
            <x-ui.button type="submit" variant="primary">{{ __('Submit request') }}</x-ui.button>
        </form>
    </section>

    <section class="absence-page__requests">
        <x-ui.heading level="2">{{ __('My absence requests') }}</x-ui.heading>
        <div class="absence-page__table-scroll">
            <table class="absence-page__table">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Sick note') }}</th>
                        <th>{{ __('Supervisor comment') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->requests as $request)
                        <tr wire:key="absence-{{ $request->id }}">
                            <td>{{ ucfirst($request->type) }}</td>
                            <td>{{ $request->start_date->toDateString() }} – {{ $request->end_date->toDateString() }}</td>
                            <td>{{ ucfirst($request->status) }}</td>
                            <td>{{ $request->type === 'sick' ? ($request->sick_note_submitted ? __('Submitted') : __('Not submitted')) : '—' }}</td>
                            <td>{{ $request->type === 'vacation' ? ($request->review_comment ?? '—') : '—' }}</td>
                            <td>
                                @if ($request->status === 'pending')
                                    <x-ui.button size="sm" variant="danger" wire:click="cancel({{ $request->id }})">{{ __('Cancel') }}</x-ui.button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="absence-page__empty-state">{{ __('No absence requests.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
