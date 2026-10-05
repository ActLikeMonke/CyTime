<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkScheduleService
{
    /**
     * Record a schedule effective from the specified date without overwriting history.
     *
     * @param  array<int, mixed>  $workDays
     */
    public function change(User $actor, User $target, int|float|string $weeklyHours, array $workDays, string $validFrom): WorkSchedule
    {
        Gate::forUser($actor)->authorize('updateSchedule', $target);

        $validated = Validator::make([
            'weeklyHours' => $weeklyHours,
            'workDays' => $workDays,
            'validFrom' => $validFrom,
        ], [
            'weeklyHours' => ['required', 'numeric', 'gt:0', 'lte:40'],
            'workDays' => ['required', 'array', 'min:1'],
            'workDays.*' => ['required', 'string', 'distinct', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri'])],
            'validFrom' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        return DB::transaction(function () use ($actor, $target, $validated): WorkSchedule {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($lockedTarget->workSchedules()->whereDate('valid_from', $validated['validFrom'])->exists()) {
                throw ValidationException::withMessages([
                    'validFrom' => 'A schedule already exists for this date. Choose a different effective date.',
                ]);
            }

            $schedule = new WorkSchedule([
                'weekly_hours' => $validated['weeklyHours'],
                'work_days' => array_values($validated['workDays']),
                'valid_from' => $validated['validFrom'],
            ]);

            $schedule->user()->associate($lockedTarget);
            $schedule->save();

            DB::table('audit_logs')->insert([
                'user_id' => $actor->id,
                'auditable_type' => WorkSchedule::class,
                'auditable_id' => $schedule->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'weekly_hours' => $validated['weeklyHours'],
                    'work_days' => array_values($validated['workDays']),
                    'valid_from' => $validated['validFrom'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $schedule;
        });
    }
}
