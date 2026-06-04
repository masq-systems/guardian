<?php

declare(strict_types=1);

use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Detectors\ThrottleHitDetector;
use Masq\Guardian\Registry\DetectorRegistry;

it('builds enabled detectors from config and skips disabled ones', function (): void {
    $registry = new DetectorRegistry(app(), [
        'throttle_hits' => ['class' => ThrottleHitDetector::class, 'enabled' => true],
        'turned_off' => ['class' => ThrottleHitDetector::class, 'enabled' => false],
    ]);

    expect($registry->keys())->toBe(['throttle_hits'])
        ->and($registry->get('throttle_hits'))->toBeInstanceOf(ThrottleHitDetector::class)
        ->and($registry->get('turned_off'))->toBeNull();
});

it('passes per-detector options through to the instance', function (): void {
    $registry = new DetectorRegistry(app(), [
        'th' => ['class' => ThrottleHitDetector::class, 'allowed_hits' => 1, 'base_points' => 50],
    ]);

    $signal = $registry->get('th')->inspect(makeUser(), ['hits' => 2]);

    expect($signal)->not->toBeNull()
        ->and($signal->points)->toBe(50);
});

it('accepts the shorthand string form', function (): void {
    $registry = new DetectorRegistry(app(), [
        'shorthand' => ThrottleHitDetector::class,
    ]);

    expect($registry->get('shorthand'))->toBeInstanceOf(ThrottleHitDetector::class);
});

it('supports runtime define and disable', function (): void {
    $registry = new DetectorRegistry(app(), [
        'a' => ['class' => ThrottleHitDetector::class],
    ]);

    $registry->define('b', ['class' => ThrottleHitDetector::class]);
    expect($registry->keys())->toContain('a', 'b');

    $registry->disable('a');
    expect($registry->get('a'))->toBeNull()
        ->and($registry->keys())->toBe(['b']);
});

it('only accepts classes implementing the Detector contract', function (): void {
    $registry = new DetectorRegistry(app(), [
        'bad' => ['class' => stdClass::class],
    ]);

    expect(fn () => $registry->all())->toThrow(InvalidArgumentException::class);
})->skip(! interface_exists(Detector::class), 'Detector contract missing');
