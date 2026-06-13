<?php

declare(strict_types=1);

namespace Masq\Guardian\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masq\Guardian\Enums\Severity;

/**
 * @property int $id
 * @property string $detector
 * @property int $points
 * @property Severity $severity
 * @property bool $fatal
 * @property string $decay
 * @property array<string, mixed>|null $evidence
 * @property string|null $reason
 * @property CarbonInterface $created_at
 * @property CarbonInterface|null $expires_at
 */
class SuspicionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'points' => 'integer',
        'severity' => Severity::class,
        'fatal' => 'boolean',
        'evidence' => 'array',
        'expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        $table = config('guardian.tables.events', 'suspicion_events');

        return is_string($table) ? $table : 'suspicion_events';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
