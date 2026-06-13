<?php

declare(strict_types=1);

namespace Masq\Guardian\Facades;

use BackedEnum;
use Illuminate\Support\Facades\Facade;
use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Engine\ScoringEngine;
use Masq\Guardian\Guardian as GuardianManager;
use Masq\Guardian\Models\TrustProfile;
use Masq\Guardian\Registry\DetectorRegistry;
use Masq\Guardian\TrackedGuardian;
use Masq\Guardian\ValueObjects\Signal;
use UnitEnum;

/**
 * @method static string defaultTrack()
 * @method static TrackedGuardian track(string|BackedEnum|UnitEnum $track)
 * @method static GuardianManager register(Detector|string $detector, ?string $track = null)
 * @method static DetectorRegistry registry(?string $track = null)
 * @method static array<int, Detector> detectors(?string $track = null)
 * @method static TrustProfile inspect(object $subject, array<string, mixed> $context = [], array<int, Detector>|null $detectors = null, ?string $track = null)
 * @method static TrustProfile run(string|BackedEnum|UnitEnum $key, object $subject, array<string, mixed> $context = [], ?string $track = null)
 * @method static TrustProfile recordThrottleHit(object $subject, string|BackedEnum|UnitEnum $limiter = 'default', array<string, mixed> $context = [], ?string $track = null)
 * @method static TrustProfile report(object $subject, Signal|array<int, Signal> $signals, array<string, mixed> $context = [], ?string $track = null)
 * @method static TrustProfile reassess(object $subject, ?string $track = null)
 * @method static TrustProfile ban(object $subject, string|BackedEnum|UnitEnum|null $reason = null, array<string, mixed> $evidence = [], ?string $track = null)
 * @method static TrustProfile clear(object $subject, ?string $track = null)
 * @method static ScoringEngine engine()
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
