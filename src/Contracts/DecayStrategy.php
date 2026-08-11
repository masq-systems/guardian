<?php

declare(strict_types=1);

namespace Guardian\Contracts;

use Carbon\CarbonInterface;

interface DecayStrategy
{
    public function remaining(int $points, CarbonInterface $occurredAt, CarbonInterface $now): int;

    public function expiresAt(CarbonInterface $occurredAt): ?CarbonInterface;
}
