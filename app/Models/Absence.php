<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property bool $sick_note_submitted
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_comment
 * @property Carbon|null $deleted_at
 */
#[Fillable(['user_id', 'type', 'start_date', 'end_date', 'status', 'sick_note_submitted', 'reviewed_by', 'reviewed_at', 'review_comment'])]
class Absence extends Model
{
    use SoftDeletes;

    public const TYPE_SICK = 'sick';

    public const TYPE_VACATION = 'vacation';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'sick_note_submitted' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
