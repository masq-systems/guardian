<?php

declare(strict_types=1);

namespace Masq\Guardian\Contracts;

use Carbon\CarbonInterface;

interface DecayStrategy
{
    /**
     * Points still "live" for an event that originally awarded $points
     * and occurred at $occurredAt, evaluated at $now.
     */
    public function remaining(int $points, CarbonInterface $occurredAt, CarbonInterface $now): int;

    /**
     * Absolute time at which the event's points reach zero, or null if
     * the points never fully decay (e.g. NoDecay, HalfLife).
     */
    public function expiresAt(CarbonInterface $occurredAt): ?CarbonInterface;
}
