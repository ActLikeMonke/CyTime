<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TimeEntryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): TimeEntry
    {
        Gate::forUser($actor)->authorize('create', [TimeEntry::class, $actor]);
        $validated = $this->validateEntry($data);
        $date = CarbonImmutable::parse($validated['date']);
        $startAt = $date->setTimeFromTimeString($validated['start_time']);
        $endAt = $date->setTimeFromTimeString($validated['end_time']);
        if ($date->isToday() && $startAt->isFuture()) {
            throw ValidationException::withMessages([
                'start_time' => 'Only today’s end time may be booked in advance.',
            ]);
        }
        $status = $date->isToday() && $endAt->isFuture()
            ? TimeEntry::STATUS_PLANNED
            : TimeEntry::STATUS_FINAL;

        return DB::transaction(function () use ($actor, $validated, $status): TimeEntry {
            $lockedUser = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->ensureNoActiveEntry($lockedUser, $validated['date']);
            $entry = $lockedUser->timeEntries()->create([
                ...$validated,
                'status' => $status,
            ]);

            $this->audit($actor, $entry, null, $this->snapshot($entry));

            return $entry;
        });
    }

    private function minutesAfterMidnight(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, TimeEntry $entry, array $data): TimeEntry
    {
        Gate::forUser($actor)->authorize('update', $entry);
        $validated = $this->validateEntry($data);
        $date = CarbonImmutable::parse($validated['date']);

        return DB::transaction(function () use ($actor, $entry, $validated, $date): TimeEntry {
            $lockedEntry = TimeEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->ensureEditableDate(CarbonImmutable::parse($lockedEntry->date));
            $this->ensureEditableDate($date);

            if (! $date->isSameDay($lockedEntry->date)) {
                $this->ensureNoActiveEntry($actor, $validated['date'], $lockedEntry->id);
            }

            $oldValues = $this->snapshot($lockedEntry);
            $endAt = $date->setTimeFromTimeString($validated['end_time']);
            $startAt = $date->setTimeFromTimeString($validated['start_time']);
            if ($date->isToday() && $startAt->isFuture()) {
                throw ValidationException::withMessages([
                    'start_time' => 'Only today’s end time may be booked in advance.',
                ]);
            }
            $lockedEntry->fill([
                ...$validated,
                'status' => $date->isToday() && $endAt->isFuture()
                    ? TimeEntry::STATUS_PLANNED
                    : TimeEntry::STATUS_FINAL,
            ]);
            $lockedEntry->save();
            $this->audit($actor, $lockedEntry, $oldValues, $this->snapshot($lockedEntry));

            return $lockedEntry;
        });
    }

    public function delete(User $actor, TimeEntry $entry): void
    {
        Gate::forUser($actor)->authorize('delete', $entry);
        $this->ensureEditableDate(CarbonImmutable::parse($entry->date));

        DB::transaction(function () use ($actor, $entry): void {
            $lockedEntry = TimeEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($lockedEntry);
            $lockedEntry->delete();
            $this->audit($actor, $lockedEntry, $oldValues, ['deleted_at' => now()->toIso8601String()]);
        });
    }

    public function clockIn(User $actor): TimeEntry
    {
        Gate::forUser($actor)->authorize('create', [TimeEntry::class, $actor]);
        $now = now();

        return DB::transaction(function () use ($actor, $now): TimeEntry {
            $lockedUser = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $date = $now->toDateString();
            $this->ensureNoActiveEntry($lockedUser, $date);

            $entry = $lockedUser->timeEntries()->create([
                'date' => $date,
                'start_time' => $now->format('H:i:s'),
                'end_time' => null,
                'break_minutes' => 0,
                'status' => TimeEntry::STATUS_IN_PROGRESS,
                'is_school_day' => false,
            ]);
            $this->audit($actor, $entry, null, $this->snapshot($entry));

            return $entry;
        });
    }

    public function clockOut(User $actor, TimeEntry $entry, int $breakMinutes = 0): TimeEntry
    {
        Gate::forUser($actor)->authorize('update', $entry);

        if ($breakMinutes < 0 || $breakMinutes > 1440) {
            throw ValidationException::withMessages([
                'breakMinutes' => 'Break minutes must be between 0 and 1440.',
            ]);
        }

        return DB::transaction(function () use ($actor, $entry, $breakMinutes): TimeEntry {
            $lockedEntry = TimeEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($lockedEntry->status !== TimeEntry::STATUS_IN_PROGRESS || ! $lockedEntry->date->isToday()) {
                throw ValidationException::withMessages([
                    'timeEntry' => 'Only an open time entry for today can be clocked out.',
                ]);
            }

            $endTime = now()->format('H:i:s');
            if (strtotime($endTime) <= strtotime((string) $lockedEntry->start_time)) {
                throw ValidationException::withMessages([
                    'timeEntry' => 'Clock-out time must be later than clock-in time.',
                ]);
            }
            if ($breakMinutes > (int) ((strtotime($endTime) - strtotime((string) $lockedEntry->start_time)) / 60)) {
                throw ValidationException::withMessages([
                    'breakMinutes' => 'The break cannot be longer than the time since clock-in.',
                ]);
            }

            $oldValues = $this->snapshot($lockedEntry);
            $lockedEntry->end_time = $endTime;
            $lockedEntry->break_minutes = $breakMinutes;
            $lockedEntry->status = TimeEntry::STATUS_FINAL;
            $lockedEntry->save();
            $this->audit($actor, $lockedEntry, $oldValues, $this->snapshot($lockedEntry));

            return $lockedEntry;
        });
    }

    public function finalizePlannedEntries(): int
    {
        $finalized = 0;

        TimeEntry::query()
            ->where('status', TimeEntry::STATUS_PLANNED)
            ->whereDate('date', today())
            ->whereTime('end_time', '<=', now()->format('H:i:s'))
            ->orderBy('id')
            ->chunkById(100, function ($entries) use (&$finalized): void {
                foreach ($entries as $entry) {
                    DB::transaction(function () use ($entry, &$finalized): void {
                        $lockedEntry = TimeEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

                        if ($lockedEntry->status !== TimeEntry::STATUS_PLANNED) {
                            return;
                        }

                        $oldValues = $this->snapshot($lockedEntry);
                        $lockedEntry->status = TimeEntry::STATUS_FINAL;
                        $lockedEntry->save();
                        $this->audit($lockedEntry->user, $lockedEntry, $oldValues, $this->snapshot($lockedEntry));
                        $finalized++;
                    });
                }
            });

        return $finalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}
     */
    private function validateEntry(array $data): array
    {
        $validated = Validator::make($data, [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'is_school_day' => ['sometimes', 'boolean'],
        ])->validate();

        $date = CarbonImmutable::parse($validated['date']);
        $start = $this->minutesAfterMidnight($validated['start_time']);
        $end = $this->minutesAfterMidnight($validated['end_time']);

        if ($end <= $start) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be later than the start time.',
            ]);
        }

        if ((int) $validated['break_minutes'] > $end - $start) {
            throw ValidationException::withMessages([
                'break_minutes' => 'The break cannot be longer than the recorded work interval.',
            ]);
        }

        $this->ensureEditableDate($date);

        return [
            'date' => $date->toDateString(),
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
            'break_minutes' => (int) $validated['break_minutes'],
            'is_school_day' => (bool) ($validated['is_school_day'] ?? false),
        ];
    }

    private function ensureEditableDate(CarbonImmutable $date): void
    {
        $earliestDate = today()->subDays((int) config('time_tracking.correction_window_days', 7));

        if ($date->isFuture() || $date->lt($earliestDate)) {
            throw ValidationException::withMessages([
                'date' => 'Time entries can only be recorded today or corrected within the past '.config('time_tracking.correction_window_days', 7).' days.',
            ]);
        }
    }

    private function ensureNoActiveEntry(User $user, string $date, ?int $exceptId = null): void
    {
        $query = $user->timeEntries()->whereDate('date', $date);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'date' => 'A time entry already exists for this date.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TimeEntry $entry): array
    {
        return [
            'date' => $entry->date->toDateString(),
            'start_time' => $entry->start_time,
            'end_time' => $entry->end_time,
            'break_minutes' => $entry->break_minutes,
            'status' => $entry->status,
            'is_school_day' => $entry->is_school_day,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function audit(User $actor, TimeEntry $entry, ?array $oldValues, array $newValues): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $actor->id,
            'auditable_type' => TimeEntry::class,
            'auditable_id' => $entry->id,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
