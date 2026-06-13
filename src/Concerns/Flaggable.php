<?php

declare(strict_types=1);

namespace Masq\Guardian\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\Models\GuardianFlag;

trait Flaggable
{
    public function guardianFlags(): MorphMany
    {
        return $this->morphMany(GuardianFlag::class, 'flaggable');
    }

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

    public function clearAutoFlags(?string $track = null): void
    {
        $this->guardianFlags()
            ->where('track', $track ?? Guardian::defaultTrack())
            ->where('state', GuardianFlag::STATE_FLAGGED)
            ->delete();
    }

    public function isGuardianFlagged(?string $track = null): bool
    {
        $query = $this->guardianFlags()->whereIn('state', GuardianFlag::INVALID_STATES);

        if ($track !== null) {
            $query->where('track', $track);
        }

        return $query->exists();
    }

    public function scopeGuardianValid(Builder $query, ?string $track = null): Builder
    {
        return $query->whereDoesntHave('guardianFlags', function (Builder $inner) use ($track): void {
            $inner->whereIn('state', GuardianFlag::INVALID_STATES);

            if ($track !== null) {
                $inner->where('track', $track);
            }
        });
    }

    public function scopeGuardianFlagged(Builder $query, ?string $track = null): Builder
    {
        return $query->whereHas('guardianFlags', function (Builder $inner) use ($track): void {
            $inner->whereIn('state', GuardianFlag::INVALID_STATES);

            if ($track !== null) {
                $inner->where('track', $track);
            }
        });
    }
}
