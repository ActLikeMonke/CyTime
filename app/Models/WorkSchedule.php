<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $weekly_hours
 * @property array<int, string> $work_days
 * @property Carbon $valid_from
 */
#[Fillable(['user_id', 'weekly_hours', 'work_days', 'valid_from'])]
class WorkSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'weekly_hours' => 'decimal:2',
            'work_days' => 'array',
            'valid_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
