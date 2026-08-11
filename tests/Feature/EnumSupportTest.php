<?php

declare(strict_types=1);

use Guardian\Enums\Decay;
use Guardian\Enums\ReviewStatus;
use Guardian\Facades\Guardian;
use Guardian\ValueObjects\Signal;

enum DetectorKey: string
{
    case Throttle = 'throttle_hits';
    case StepRate = 'step_rate';
}

enum Limiter: string
{
    case Login = 'login';
}

it('accepts enums for detector and decay on a signal', function (): void {
    $signal = Signal::soft(DetectorKey::StepRate, 10, decay: Decay::Linear);

    expect($signal->detector)->toBe('step_rate')
        ->and($signal->decay)->toBe('linear');
});

it('accepts an enum limiter for recordThrottleHit', function (): void {
    $user = makeUser();

    for ($i = 0; $i < 7; $i++) {
        Guardian::recordThrottleHit($user, Limiter::Login);
    }

    expect($user->fresh()->suspicionScore())->toBeGreaterThan(0);
});

it('accepts an enum key in the registry', function (): void {
    expect(Guardian::registry()->get(DetectorKey::Throttle))->not->toBeNull();
});

it('casts the moderator review status to an enum', function (): void {
    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('test', 90, decay: 'none'));

    $review = $user->moderatorReviews()->sole();
    expect($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->isPending())->toBeTrue();
});
