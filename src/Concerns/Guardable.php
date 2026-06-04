<?php

declare(strict_types=1);

namespace Masq\Guardian\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\Models\ModeratorReview;
use Masq\Guardian\Models\SuspicionEvent;
use Masq\Guardian\Models\TrustProfile;
use Masq\Guardian\Support\States;
use Masq\Guardian\Support\TrustCache;
use Masq\Guardian\ValueObjects\Signal;

/**
 * Apply to any model that can accrue suspicion (e.g. App\Models\User).
 *
 * All read/write helpers take an optional `$track` — an independent trust track
 * (default: config `guardian.default_track`). Hot reads are served from the
 * cache via TrustCache, so middleware and views don't hit the database.
 *
 * @property-read Collection<int, TrustProfile> $trustProfiles
 * @property-read Collection<int, SuspicionEvent> $suspicionEvents
 *
 * @mixin Model
 */
trait Guardable
{
    /** @return MorphMany<TrustProfile, $this> */
    public function trustProfiles(): MorphMany
    {
        return $this->morphMany(TrustProfile::class, 'subject');
    }

    /** @return MorphMany<SuspicionEvent, $this> */
    public function suspicionEvents(): MorphMany
    {
        return $this->morphMany(SuspicionEvent::class, 'subject');
    }

    /** @return MorphMany<ModeratorReview, $this> */
    public function moderatorReviews(): MorphMany
    {
        return $this->morphMany(ModeratorReview::class, 'subject');
    }

    public function trustState(?string $track = null): TrustStateContract
    {
        return app(States::class)->fromKey($this->guardianStanding($track)['state']);
    }

    public function suspicionScore(?string $track = null): int
    {
        return $this->guardianStanding($track)['score'];
    }

    public function isBanned(?string $track = null): bool
    {
        return $this->guardianStanding($track)['banned'];
    }

    public function isFlagged(?string $track = null): bool
    {
        $states = app(States::class);

        return $this->trustState($track)->level() > $states->base()->level();
    }

    public function needsReview(?string $track = null): bool
    {
        return $this->moderatorReviews()
            ->where('track', $track ?? Guardian::defaultTrack())
            ->where('status', ModeratorReview::STATUS_PENDING)
            ->exists();
    }

    /**
     * Raise one or more signals against this subject in a track and re-evaluate.
     *
     * @param  Signal|array<int, Signal>  $signals
     * @param  array<string, mixed>  $context
     */
    public function raiseSuspicion(Signal|array $signals, array $context = [], ?string $track = null): TrustProfile
    {
        return Guardian::report($this, $signals, $context, $track);
    }

    /**
     * Manually ban this subject in a track (permanent). `$reason` accepts a
     * string or an enum.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function ban(string|\BackedEnum|\UnitEnum|null $reason = null, array $evidence = [], ?string $track = null): TrustProfile
    {
        return Guardian::ban($this, $reason, $evidence, $track);
    }

    /** Lift a ban / forgive this subject in a track (clears events, resets to base). */
    public function unban(?string $track = null): TrustProfile
    {
        return Guardian::clear($this, $track);
    }

    /**
     * @return array{score: int, state: string, banned: bool}
     */
    protected function guardianStanding(?string $track = null): array
    {
        return app(TrustCache::class)->standing($this, $track ?? Guardian::defaultTrack());
    }
}
