<?php

declare(strict_types=1);

namespace Masq\Guardian;

use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Models\TrustProfile;
use Masq\Guardian\ValueObjects\Signal;

/**
 * Thin wrapper that binds every Guardian call to a single track, so you can
 * write `Guardian::track('behavior')->inspect($user, $ctx)`.
 */
final class TrackedGuardian
{
    public function __construct(
        private readonly Guardian $guardian,
        private readonly string $track,
    ) {}

    public function register(Detector|string $detector): self
    {
        $this->guardian->register($detector, $this->track);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, Detector>|null  $detectors
     */
    public function inspect(object $subject, array $context = [], ?array $detectors = null): TrustProfile
    {
        return $this->guardian->inspect($subject, $context, $detectors, $this->track);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function run(string|\BackedEnum|\UnitEnum $key, object $subject, array $context = []): TrustProfile
    {
        return $this->guardian->run($key, $subject, $context, $this->track);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordThrottleHit(object $subject, string|\BackedEnum|\UnitEnum $limiter = 'default', array $context = []): TrustProfile
    {
        return $this->guardian->recordThrottleHit($subject, $limiter, $context, $this->track);
    }

    /**
     * @param  Signal|array<int, Signal>  $signals
     * @param  array<string, mixed>  $context
     */
    public function report(object $subject, Signal|array $signals, array $context = []): TrustProfile
    {
        return $this->guardian->report($subject, $signals, $context, $this->track);
    }

    public function reassess(object $subject): TrustProfile
    {
        return $this->guardian->reassess($subject, $this->track);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function ban(object $subject, string|\BackedEnum|\UnitEnum|null $reason = null, array $evidence = []): TrustProfile
    {
        return $this->guardian->ban($subject, $reason, $evidence, $this->track);
    }

    public function clear(object $subject): TrustProfile
    {
        return $this->guardian->clear($subject, $this->track);
    }
}
