<?php

declare(strict_types=1);

use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\Facades\Guardian;

it('does not score throttle hits below the threshold', function (): void {
    $user = makeUser();

    for ($i = 0; $i < 4; $i++) {                 // threshold is 5
        Guardian::recordThrottleHit($user, 'login');
    }

    $fresh = $user->fresh();
    expect($fresh->suspicionScore())->toBe(0)
        ->and($fresh->trustState())->toBe(TrustState::Trusted);
});

it('scores and flags a subject that keeps hitting the throttle', function (): void {
    $user = makeUser();

    for ($i = 0; $i < 8; $i++) {
        Guardian::recordThrottleHit($user, 'login');
    }

    $fresh = $user->fresh();
    expect($fresh->suspicionScore())->toBeGreaterThan(0)
        ->and($fresh->isFlagged())->toBeTrue()
        ->and($fresh->isBanned())->toBeFalse(); // throttle is soft — never auto-bans
});

it('is a no-op when the throttle detector is disabled', function (): void {
    // Disable before Guardian is first resolved so the lazy singletons pick it up.
    config()->set('guardian.tracks.default.detectors.throttle_hits.enabled', false);

    $user = makeUser();

    for ($i = 0; $i < 10; $i++) {
        Guardian::recordThrottleHit($user, 'login');
    }

    expect($user->fresh()->suspicionScore())->toBe(0)
        ->and(Guardian::registry()->get('throttle_hits'))->toBeNull();
});
