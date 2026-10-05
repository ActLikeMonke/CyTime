<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property string $start_time
 * @property string|null $end_time
 * @property int $break_minutes
 * @property string $status
 * @property bool $is_school_day
 */
#[Fillable(['user_id', 'date', 'start_time', 'end_time', 'break_minutes', 'status', 'is_school_day'])]
class TimeEntry extends Model
{
    use SoftDeletes;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINAL = 'final';

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'break_minutes' => 'integer',
            'is_school_day' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function netMinutes(): int
    {
        if ($this->end_time === null) {
            return 0;
        }

        $start = $this->minutesAfterMidnight((string) $this->start_time);
        $end = $this->minutesAfterMidnight((string) $this->end_time);

        return max(0, $end - $start - $this->break_minutes);
    }

    private function minutesAfterMidnight(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
