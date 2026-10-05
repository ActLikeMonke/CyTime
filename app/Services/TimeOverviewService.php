<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

class TimeOverviewService
{
    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, entries: \Illuminate\Database\Eloquent\Collection<int, TimeEntry>, absences: Collection<int, \stdClass>, total_minutes: int}
     */
    public function for(User $user, string $period, string $referenceDate): array
    {
        $validated = Validator::make([
            'period' => $period,
            'referenceDate' => $referenceDate,
        ], [
            'period' => ['required', 'in:day,week,month'],
            'referenceDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        $date = CarbonImmutable::parse($validated['referenceDate']);

        [$start, $end] = match ($period) {
            'day' => [$date, $date],
            'week' => [$date->startOfWeek(), $date->endOfWeek()],
            'month' => [$date->startOfMonth(), $date->endOfMonth()],
            default => throw new LogicException('Validated time overview period was unsupported.'),
        };

        $entries = $user->timeEntries()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        $absences = DB::table('absences')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get(['type', 'status', 'start_date', 'end_date', 'sick_note_submitted']);

        return [
            'start' => $start,
            'end' => $end,
            'entries' => $entries,
            'absences' => $absences,
            'total_minutes' => $entries
                ->where('status', TimeEntry::STATUS_FINAL)
                ->sum(fn (TimeEntry $entry): int => $entry->netMinutes()),
        ];
    }
}
