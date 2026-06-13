<?php

declare(strict_types=1);

namespace Masq\Guardian\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $score
 * @property string|null $state
 * @property string|null $track
 * @property CarbonInterface|null $flagged_at
 * @property CarbonInterface|null $banned_at
 * @property CarbonInterface|null $evaluated_at
 */
class TrustProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'score' => 'integer',
        'flagged_at' => 'datetime',
        'banned_at' => 'datetime',
        'evaluated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        $table = config('guardian.tables.profiles', 'trust_profiles');

        return is_string($table) ? $table : 'trust_profiles';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
