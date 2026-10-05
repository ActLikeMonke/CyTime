<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AbsenceService
{
    public function __construct(private readonly LeaveBalanceService $leaveBalances) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(User $actor, array $data): Absence
    {
        Gate::forUser($actor)->authorize('create', [Absence::class, $actor]);
        $validated = Validator::make($data, [
            'type' => ['required', Rule::in([Absence::TYPE_SICK, Absence::TYPE_VACATION])],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'sick_note_submitted' => ['sometimes', 'boolean'],
        ])->validate();
        $start = CarbonImmutable::parse($validated['start_date']);
        $end = CarbonImmutable::parse($validated['end_date']);
        if ($validated['type'] === Absence::TYPE_VACATION
            && $this->leaveBalances->daysForRequest($actor, $start, $end) === 0) {
            throw ValidationException::withMessages([
                'start_date' => 'Vacation must include at least one scheduled workday that is not an NRW public holiday.',
            ]);
        }

        return DB::transaction(function () use ($actor, $validated, $start, $end): Absence {
            $lockedUser = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $overlapExists = $lockedUser->absences()
                ->whereIn('status', [Absence::STATUS_PENDING, Absence::STATUS_APPROVED])
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'start_date' => 'This period overlaps an existing pending or approved absence.',
                ]);
            }

            $absence = $lockedUser->absences()->create([
                'type' => $validated['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => Absence::STATUS_PENDING,
                'sick_note_submitted' => $validated['type'] === Absence::TYPE_SICK
                    && (bool) ($validated['sick_note_submitted'] ?? false),
            ]);
            $this->audit($actor, $absence, null, $this->snapshot($absence));

            return $absence;
        });
    }

    public function cancel(User $actor, Absence $absence): void
    {
        Gate::forUser($actor)->authorize('delete', $absence);

        DB::transaction(function () use ($actor, $absence): void {
            $lockedAbsence = Absence::query()->whereKey($absence->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $lockedAbsence);
            $oldValues = $this->snapshot($lockedAbsence);
            $lockedAbsence->delete();
            $this->audit($actor, $lockedAbsence, $oldValues, ['deleted_at' => now()->toIso8601String()]);
        });
    }

    public function review(User $actor, Absence $absence, string $decision, ?string $comment = null): Absence
    {
        Gate::forUser($actor)->authorize('review', $absence);
        $validated = Validator::make([
            'decision' => $decision,
            'comment' => $comment,
        ], [
            'decision' => ['required', Rule::in([Absence::STATUS_APPROVED, Absence::STATUS_REJECTED])],
            'comment' => ['nullable', 'string', 'max:500'],
        ])->validate();

        if ($absence->type === Absence::TYPE_SICK && filled($validated['comment'])) {
            throw ValidationException::withMessages([
                'comment' => 'Comments are disabled for sick reports to avoid storing sensitive health information.',
            ]);
        }

        return DB::transaction(function () use ($actor, $absence, $validated): Absence {
            $lockedAbsence = Absence::query()->whereKey($absence->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('review', $lockedAbsence);
            $oldValues = $this->snapshot($lockedAbsence);
            $lockedAbsence->status = $validated['decision'];
            $lockedAbsence->reviewed_by = $actor->id;
            $lockedAbsence->reviewed_at = Carbon::instance(now());

            if (Schema::hasColumn('absences', 'review_comment')) {
                $lockedAbsence->review_comment = $lockedAbsence->type === Absence::TYPE_VACATION
                    ? ($validated['comment'] ?: null)
                    : null;
            }

            $lockedAbsence->save();
            $this->audit($actor, $lockedAbsence, $oldValues, $this->snapshot($lockedAbsence));

            return $lockedAbsence;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Absence $absence): array
    {
        return [
            'type' => $absence->type,
            'start_date' => $absence->start_date->toDateString(),
            'end_date' => $absence->end_date->toDateString(),
            'status' => $absence->status,
            'sick_note_submitted' => $absence->sick_note_submitted,
            'reviewed_by' => $absence->reviewed_by,
            'reviewed_at' => $absence->reviewed_at?->toIso8601String(),
            'review_comment' => Schema::hasColumn('absences', 'review_comment') && $absence->type === Absence::TYPE_VACATION
                ? $absence->review_comment
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function audit(User $actor, Absence $absence, ?array $oldValues, array $newValues): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $actor->id,
            'auditable_type' => Absence::class,
            'auditable_id' => $absence->id,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
