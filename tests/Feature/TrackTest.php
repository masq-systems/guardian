<?php

declare(strict_types=1);

use Guardian\Enums\TrustState;
use Guardian\Facades\Guardian;
use Guardian\ValueObjects\Signal;

it('tracks tracks independently', function (): void {
    $user = makeUser();

    Guardian::track('behavior')->report($user, Signal::soft('chat', 90, decay: 'none'));

    expect($user->trustState('behavior'))->toBe(TrustState::Review)
        ->and($user->suspicionScore('behavior'))->toBe(90)
        ->and($user->trustState())->toBe(TrustState::Trusted)
        ->and($user->suspicionScore())->toBe(0);
});

it('bans in one track without touching another', function (): void {
    $user = makeUser();

    Guardian::track('anticheat')->ban($user);

    expect($user->isBanned('anticheat'))->toBeTrue()
        ->and($user->isBanned('behavior'))->toBeFalse()
        ->and($user->isBanned())->toBeFalse();
});

it('clears only the targeted track', function (): void {
    $user = makeUser();
    Guardian::track('behavior')->report($user, Signal::soft('x', 90, decay: 'none'));
    Guardian::track('anticheat')->report($user, Signal::soft('y', 90, decay: 'none'));

    Guardian::track('behavior')->clear($user);

    expect($user->trustState('behavior'))->toBe(TrustState::Trusted)
        ->and($user->trustState('anticheat'))->toBe(TrustState::Review);
});

it('opens separate moderation cases per track', function (): void {
    $user = makeUser();
    Guardian::track('behavior')->report($user, Signal::soft('x', 90, decay: 'none'));
    Guardian::track('anticheat')->report($user, Signal::soft('y', 90, decay: 'none'));

    expect($user->moderatorReviews()->where('track', 'behavior')->count())->toBe(1)
        ->and($user->moderatorReviews()->where('track', 'anticheat')->count())->toBe(1);
});
