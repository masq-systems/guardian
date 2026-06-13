<?php

declare(strict_types=1);

namespace Masq\Guardian\Decay;

use Carbon\CarbonInterface;
use Masq\Guardian\Contracts\DecayStrategy;

final class HalfLifeDecay implements DecayStrategy
{
    public function __construct(private readonly int $days = 14) {}

    public function remaining(int $points, CarbonInterface $occurredAt, CarbonInterface $now): int
    {
        if ($this->days <= 0) {
            return 0;
        }

        $elapsedDays = max(0.0, $occurredAt->diffInSeconds($now, true) / 86400);
        $factor = 2 ** (-$elapsedDays / $this->days);

        return (int) round($points * $factor);
    }

    public function expiresAt(CarbonInterface $occurredAt): CarbonInterface
    {

        return $occurredAt->copy()->addDays($this->days * 10);
    }
}
