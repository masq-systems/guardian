<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Guardian\Decay\HalfLifeDecay;
use Guardian\Decay\LinearDecay;
use Guardian\Decay\NoDecay;

it('keeps all points with NoDecay', function (): void {
    $strategy = new NoDecay;
    $start = CarbonImmutable::parse('2026-01-01');

    expect($strategy->remaining(100, $start, $start->addYears(5)))->toBe(100)
        ->and($strategy->expiresAt($start))->toBeNull();
});

it('fades points linearly to zero', function (): void {
    $strategy = new LinearDecay(days: 30);
    $start = CarbonImmutable::parse('2026-01-01');

    expect($strategy->remaining(100, $start, $start))->toBe(100)
        ->and($strategy->remaining(100, $start, $start->addDays(15)))->toBe(50)
        ->and($strategy->remaining(100, $start, $start->addDays(30)))->toBe(0)
        ->and($strategy->remaining(100, $start, $start->addDays(60)))->toBe(0);
});

it('halves points each half-life', function (): void {
    $strategy = new HalfLifeDecay(days: 14);
    $start = CarbonImmutable::parse('2026-01-01');

    expect($strategy->remaining(100, $start, $start))->toBe(100)
        ->and($strategy->remaining(100, $start, $start->addDays(14)))->toBe(50)
        ->and($strategy->remaining(100, $start, $start->addDays(28)))->toBe(25);
});
