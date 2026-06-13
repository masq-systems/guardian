<?php

declare(strict_types=1);

namespace Masq\Guardian\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\Models\ModeratorReview;
use Masq\Guardian\Models\SuspicionEvent;
use Masq\Guardian\Models\TrustProfile;
use Masq\Guardian\Support\States;
use Masq\Guardian\Support\TrustCache;
use Masq\Guardian\ValueObjects\Signal;

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
