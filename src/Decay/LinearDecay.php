<?php

declare(strict_types=1);

namespace Masq\Guardian\Decay;

use Carbon\CarbonInterface;
use Masq\Guardian\Contracts\DecayStrategy;

final class LinearDecay implements DecayStrategy
{
    public function __construct(private readonly int $days = 30) {}

    public function remaining(int $points, CarbonInterface $occurredAt, CarbonInterface $now): int
    {
        if ($this->days <= 0) {
            return 0;
        }

        $elapsed = max(0.0, $occurredAt->diffInSeconds($now, true));
        $window = $this->days * 86400;
        $fraction = 1.0 - ($elapsed / $window);

        if ($fraction <= 0) {
            return 0;
        }

        return (int) round($points * $fraction);
    }

    public function expiresAt(CarbonInterface $occurredAt): CarbonInterface
    {
        return $occurredAt->copy()->addDays($this->days);
    }
}
