<?php

declare(strict_types=1);

namespace Guardian\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GuardianFlag extends Model
{
    public const STATE_FLAGGED = 'flagged';

    public const STATE_CLEARED = 'cleared';

    public const STATE_CONFIRMED = 'confirmed';

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

    /**
     * @return MorphTo<Model, $this>
     */
    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }
}
