<?php

declare(strict_types=1);

namespace Masq\Guardian\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\Models\GuardianFlag;

/**
 * Apply to any model whose individual records can be marked invalid by Guardian
 * (e.g. a daily activity row).
 *
 * Unlike {@see Guardable} — which scores a *subject* (a user) over time — this
 * marks a single *record* as not-to-be-counted. Guardian only *marks*; it never
 * deletes the data. Whether a flagged record is excluded is the consuming app's
 * decision, expressed by querying with {@see scopeGuardianValid()}. Marks are
 * reversible: a moderator can clear (dismiss) or confirm (uphold) them.
 *
 * All helpers take an optional `$track` (default: `guardian.default_track`), so
 * the same record can be flagged independently on separate concerns.
 *
 * @property-read Collection<int, GuardianFlag> $guardianFlags
 *
 * @mixin Model
 */
trait Flaggable
{
    /** @return MorphMany<GuardianFlag, $this> */
    public function guardianFlags(): MorphMany
    {
        return $this->morphMany(GuardianFlag::class, 'flaggable');
    }

    /**
     * Mark this record invalid. Idempotent for auto-flags: one `flagged` row
     * per track is kept (re-flagging updates reason/evidence). Existing
     * moderator decisions (`cleared` / `confirmed`) are left untouched.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function flagAsInvalid(
        ?string $reason = null,
        array $evidence = [],
        string $severity = 'hard',
        ?string $track = null,
    ): GuardianFlag {
        $track ??= Guardian::defaultTrack();

        return $this->guardianFlags()->updateOrCreate(
            ['track' => $track, 'state' => GuardianFlag::STATE_FLAGGED],
            ['reason' => $reason, 'severity' => $severity, 'evidence' => $evidence ?: null],
        );
    }

    /**
     * Remove auto-raised flags (`flagged`) for a track — e.g. when a day is
     * re-evaluated and no longer trips a detector. Moderator decisions
     * (`cleared` / `confirmed`) are preserved.
     */
    public function clearAutoFlags(?string $track = null): void
    {
        $this->guardianFlags()
            ->where('track', $track ?? Guardian::defaultTrack())
            ->where('state', GuardianFlag::STATE_FLAGGED)
            ->delete();
    }

    /** Is this record currently marked invalid (flagged or confirmed)? */
    public function isGuardianFlagged(?string $track = null): bool
    {
        $query = $this->guardianFlags()->whereIn('state', GuardianFlag::INVALID_STATES);

        if (null !== $track) {
            $query->where('track', $track);
        }

        return $query->exists();
    }

    /**
     * Scope: only records with NO invalidating flag (the valid set).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGuardianValid(Builder $query, ?string $track = null): Builder
    {
        return $query->whereDoesntHave('guardianFlags', function (Builder $inner) use ($track): void {
            $inner->whereIn('state', GuardianFlag::INVALID_STATES);

            if (null !== $track) {
                $inner->where('track', $track);
            }
        });
    }

    /**
     * Scope: only records currently marked invalid.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGuardianFlagged(Builder $query, ?string $track = null): Builder
    {
        return $query->whereHas('guardianFlags', function (Builder $inner) use ($track): void {
            $inner->whereIn('state', GuardianFlag::INVALID_STATES);

            if (null !== $track) {
                $inner->where('track', $track);
            }
        });
    }
}
