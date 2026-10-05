<?php

use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Absence approvals')] class extends Component {
    public array $comments = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Absence::class);
    }

    #[Computed]
    public function pendingRequests()
    {
        Gate::authorize('viewAny', Absence::class);

        return Absence::query()
            ->with('user')
            ->where('status', Absence::STATUS_PENDING)
            ->orderBy('start_date')
            ->get();
    }

    public function approve(int $absenceId): void
    {
        $absence = Absence::query()->findOrFail($absenceId);
        Gate::authorize('review', $absence);
        $comment = $this->validatedComment($absence);
        app(AbsenceService::class)->review(
            auth()->user(),
            $absence,
            Absence::STATUS_APPROVED,
            $comment,
        );
        unset($this->pendingRequests);
        unset($this->comments[$absenceId]);
    }

    public function reject(int $absenceId): void
    {
        $absence = Absence::query()->findOrFail($absenceId);
        Gate::authorize('review', $absence);
        $comment = $this->validatedComment($absence);
        app(AbsenceService::class)->review(
            auth()->user(),
            $absence,
            Absence::STATUS_REJECTED,
            $comment,
        );
        unset($this->pendingRequests);
        unset($this->comments[$absenceId]);
    }

    private function validatedComment(Absence $absence): ?string
    {
        if ($absence->type === Absence::TYPE_SICK) {
            return null;
        }

        $validated = $this->validate([
            "comments.{$absence->id}" => ['nullable', 'string', 'max:500'],
        ]);

        return $validated['comments'][$absence->id] ?? null;
    }
}; ?>

<div class="supervisor-absences">
    <header class="supervisor-absences__header">
        <div class="app-page-title-row">
            <x-ui.heading level="1" size="xl">{{ __('Absence approvals') }}</x-ui.heading>
            <x-app-page-actions />
        </div>
        <x-ui.text>{{ __('Review sick-day reports and vacation requests. Sick reports count as absences as soon as they are submitted.') }}</x-ui.text>
    </header>

    <section class="supervisor-absences__requests">
        <x-ui.heading level="2">{{ __('Pending requests') }}</x-ui.heading>
        <div class="supervisor-absences__request-list">
            @forelse ($this->pendingRequests as $request)
                <article wire:key="pending-absence-{{ $request->id }}" class="supervisor-absences__request">
                    <div class="supervisor-absences__request-header">
                        <div class="supervisor-absences__request-summary">
                            <x-ui.heading level="3">{{ $request->user->name }} · {{ ucfirst($request->type) }}</x-ui.heading>
                            <x-ui.text>{{ $request->start_date->toDateString() }} – {{ $request->end_date->toDateString() }}</x-ui.text>
                            @if ($request->type === Absence::TYPE_SICK)
                                <x-ui.text>{{ __('Sick note (AU): :status', ['status' => $request->sick_note_submitted ? __('submitted') : __('not submitted')]) }}</x-ui.text>
                            @endif
                        </div>
                        <x-ui.badge variant="warning">{{ __('Pending') }}</x-ui.badge>
                    </div>

                    @if ($request->type === Absence::TYPE_VACATION)
                        <x-ui.input wire:model="comments.{{ $request->id }}" :label="__('Optional supervisor comment')" maxlength="500" />
                        @error("comments.{$request->id}")
                            <p class="supervisor-absences__error" role="alert">{{ $message }}</p>
                        @enderror
                    @else
                        <x-ui.text>{{ __('Comments are disabled for sick reports to avoid recording sensitive health information.') }}</x-ui.text>
                    @endif    
                    <div class="supervisor-absences__actions">
                        <x-ui.button variant="primary" wire:click="approve({{ $request->id }})">{{ __('Approve') }}</x-ui.button>
                        <x-ui.button variant="danger" wire:click="reject({{ $request->id }})">{{ __('Reject') }}</x-ui.button>
                    </div>
                </article>
            @empty
                <x-ui.text>{{ __('There are no pending absence requests.') }}</x-ui.text>
            @endforelse
        </div>
    </section>
</div>
