<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\DB;

class UserDataExportService
{
    /**
     * @return array{
     *     generated_at: string,
     *     profile: array<string, mixed>,
     *     work_schedules: array<int, array<string, mixed>>,
     *     time_entries: array<int, array<string, mixed>>,
     *     absences: array<int, array<string, mixed>>,
     *     audit_history: array<int, array<string, mixed>>
     * }
     */
    public function forUser(User $user): array
    {
        $schedules = $user->workSchedules()->orderBy('valid_from')->get();
        $entries = $user->timeEntries()->withTrashed()->orderBy('date')->orderBy('start_time')->get();
        $absences = $user->absences()->withTrashed()->orderBy('start_date')->get();
        $auditHistory = DB::table('audit_logs')
            ->where(function ($query) use ($user, $schedules, $entries, $absences): void {
                $query->where(function ($query) use ($user): void {
                    $query->where('auditable_type', User::class)->where('auditable_id', $user->id);
                });

                foreach ([
                    WorkSchedule::class => $schedules->modelKeys(),
                    TimeEntry::class => $entries->modelKeys(),
                    Absence::class => $absences->modelKeys(),
                ] as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(function ($query) use ($type, $ids): void {
                            $query->where('auditable_type', $type)->whereIn('auditable_id', $ids);
                        });
                    }
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'created_at'])
            ->map(fn (object $log): array => [
                'id' => $log->id,
                'record_type' => $log->auditable_type,
                'record_id' => $log->auditable_id,
                'old_values' => $this->auditValues($log->old_values),
                'new_values' => $this->auditValues($log->new_values),
                'created_at' => $log->created_at,
            ])
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'birth_date' => $user->birth_date?->toDateString(),
                'role' => $user->role,
                'annual_leave_days' => $user->annual_leave_days,
                'privacy_notice_acknowledged_at' => $user->privacy_notice_acknowledged_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'work_schedules' => $schedules->map(fn (WorkSchedule $schedule): array => [
                'id' => $schedule->id,
                'weekly_hours' => $schedule->weekly_hours,
                'work_days' => $schedule->work_days,
                'valid_from' => $schedule->valid_from->toDateString(),
                'created_at' => $schedule->created_at?->toIso8601String(),
                'updated_at' => $schedule->updated_at?->toIso8601String(),
            ])->all(),
            'time_entries' => $entries->map(fn (TimeEntry $entry): array => [
                'id' => $entry->id,
                'date' => $entry->date->toDateString(),
                'start_time' => $entry->start_time,
                'end_time' => $entry->end_time,
                'break_minutes' => $entry->break_minutes,
                'status' => $entry->status,
                'is_school_day' => $entry->is_school_day,
                'created_at' => $entry->created_at?->toIso8601String(),
                'updated_at' => $entry->updated_at?->toIso8601String(),
                'deleted_at' => $entry->deleted_at?->toIso8601String(),
            ])->all(),
            'absences' => $absences->map(fn (Absence $absence): array => [
                'id' => $absence->id,
                'type' => $absence->type,
                'start_date' => $absence->start_date->toDateString(),
                'end_date' => $absence->end_date->toDateString(),
                'status' => $absence->status,
                'sick_note_submitted' => $absence->sick_note_submitted,
                'reviewed_at' => $absence->reviewed_at?->toIso8601String(),
                'review_comment' => $absence->review_comment,
                'created_at' => $absence->created_at?->toIso8601String(),
                'updated_at' => $absence->updated_at?->toIso8601String(),
                'deleted_at' => $absence->deleted_at?->toIso8601String(),
            ])->all(),
            'audit_history' => $auditHistory,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function auditValues(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $values = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        unset($values['reviewed_by']);

        return $values;
    }
}
