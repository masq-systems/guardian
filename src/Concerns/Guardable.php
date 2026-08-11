<?php

declare(strict_types=1);

namespace Guardian\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Guardian\Contracts\TrustStateContract;
use Guardian\Facades\Guardian;
use Guardian\Models\ModeratorReview;
use Guardian\Models\SuspicionEvent;
use Guardian\Models\TrustProfile;
use Guardian\Support\States;
use Guardian\Support\TrustCache;
use Guardian\ValueObjects\Signal;

trait Guardable
{
    public function trustProfiles(): MorphMany
    {
        return $this->morphMany(TrustProfile::class, 'subject');
    }

    public function suspicionEvents(): MorphMany
    {
        return $this->morphMany(SuspicionEvent::class, 'subject');
    }

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

    public function raiseSuspicion(Signal|array $signals, array $context = [], ?string $track = null): TrustProfile
    {
        return Guardian::report($this, $signals, $context, $track);
    }

    public function ban(string|\BackedEnum|\UnitEnum|null $reason = null, array $evidence = [], ?string $track = null): TrustProfile
    {
        return Guardian::ban($this, $reason, $evidence, $track);
    }

    public function unban(?string $track = null): TrustProfile
    {
        return Guardian::clear($this, $track);
    }

    protected function guardianStanding(?string $track = null): array
    {
        return app(TrustCache::class)->standing($this, $track ?? Guardian::defaultTrack());
    }
}
