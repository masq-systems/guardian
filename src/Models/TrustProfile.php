<?php

declare(strict_types=1);

namespace Masq\Guardian\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Denormalized current trust standing for a subject, per track.
 *
 * `state` is stored as the state's string key (the active state enum is
 * pluggable — see Support\States — so it is not cast to a fixed enum here).
 *
 * @property int $id
 * @property string $track
 * @property int $score
 * @property string $state
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

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
