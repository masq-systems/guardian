<?php

declare(strict_types=1);

namespace Masq\Guardian\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masq\Guardian\Enums\ReviewStatus;

/**
 * A subject queued for human moderation.
 *
 * @property int $id
 * @property ReviewStatus $status
 * @property string|null $reason
 * @property int $score_at_flag
 * @property array<string, mixed> $evidence
 * @property string|null $decided_by
 * @property string|null $notes
 * @property CarbonInterface|null $decided_at
 */
class ModeratorReview extends Model
{
    // String aliases for queries; `status` itself casts to ReviewStatus.
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_PENALIZED = 'penalized';

    public const STATUS_BANNED = 'banned';

    protected $guarded = [];

    protected $casts = [
        'status' => ReviewStatus::class,
        'score_at_flag' => 'integer',
        'evidence' => 'array',
        'decided_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function getTable(): string
    {
        $table = config('guardian.tables.reviews', 'moderator_reviews');

        return is_string($table) ? $table : 'moderator_reviews';
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }
}
