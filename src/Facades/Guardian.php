<?php

declare(strict_types=1);

namespace Masq\Guardian\Facades;

use Illuminate\Support\Facades\Facade;
use Masq\Guardian\Guardian as GuardianManager;

/**
 * @method static string defaultTrack()
 * @method static \Masq\Guardian\TrackedGuardian track(string|\BackedEnum|\UnitEnum $track)
 * @method static GuardianManager register(\Masq\Guardian\Contracts\Detector|string $detector, ?string $track = null)
 * @method static \Masq\Guardian\Registry\DetectorRegistry registry(?string $track = null)
 * @method static array<int, \Masq\Guardian\Contracts\Detector> detectors(?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile inspect(object $subject, array<string, mixed> $context = [], array<int, \Masq\Guardian\Contracts\Detector>|null $detectors = null, ?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile run(string|\BackedEnum|\UnitEnum $key, object $subject, array<string, mixed> $context = [], ?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile recordThrottleHit(object $subject, string|\BackedEnum|\UnitEnum $limiter = 'default', array<string, mixed> $context = [], ?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile report(object $subject, \Masq\Guardian\ValueObjects\Signal|array<int, \Masq\Guardian\ValueObjects\Signal> $signals, array<string, mixed> $context = [], ?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile reassess(object $subject, ?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile ban(object $subject, string|\BackedEnum|\UnitEnum|null $reason = null, array<string, mixed> $evidence = [], ?string $track = null)
 * @method static \Masq\Guardian\Models\TrustProfile clear(object $subject, ?string $track = null)
 * @method static \Masq\Guardian\Engine\ScoringEngine engine()
 *
 * @see GuardianManager
 */
final class Guardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GuardianManager::class;
    }
}
