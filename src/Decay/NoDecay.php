<?php

declare(strict_types=1);

namespace Guardian\Decay;

use Carbon\CarbonInterface;
use Guardian\Contracts\DecayStrategy;

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
