<?php

declare(strict_types=1);

namespace Masq\Guardian\Decay;

use Carbon\CarbonInterface;
use Masq\Guardian\Contracts\DecayStrategy;

/** Points never decay. Use for hard, permanent violations. */
final class NoDecay implements DecayStrategy
{
    public function remaining(int $points, CarbonInterface $occurredAt, CarbonInterface $now): int
    {
        return $points;
    }

    public function expiresAt(CarbonInterface $occurredAt): ?CarbonInterface
    {
        return null;
    }
}
