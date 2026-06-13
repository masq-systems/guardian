<?php

declare(strict_types=1);

namespace Masq\Guardian\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A reversible "this record is invalid" mark against any flaggable model.
 *
 * @property int $id
 * @property string $flaggable_type
 * @property int $flaggable_id
 * @property string $track
 * @property string|null $reason
 * @property string $severity
 * @property string $state
 * @property array<string, mixed>|null $evidence
 * @property CarbonInterface|null $cleared_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class GuardianFlag extends Model
{
    /** Auto-raised by a detector; excludes the record. */
    public const STATE_FLAGGED = 'flagged';

    /** Moderator dismissed the flag; the record counts again. */
    public const STATE_CLEARED = 'cleared';

    /** Moderator upheld the flag; the record stays excluded. */
    public const STATE_CONFIRMED = 'confirmed';

    /** States that mean the record is invalid (excluded). */
    public const INVALID_STATES = [self::STATE_FLAGGED, self::STATE_CONFIRMED];

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'cleared_at' => 'datetime',
    ];

    public function getTable(): string
    {
        $table = config('guardian.tables.flags', 'guardian_flags');

        return is_string($table) ? $table : 'guardian_flags';
    }

    /** @return MorphTo<Model, $this> */
    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }
}
