<?php

declare(strict_types=1);

use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\ValueObjects\Signal;

it('escalates trust state as soft points accrue', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('test', 25, decay: 'none'));
    expect($user->fresh()->trustState())->toBe(TrustState::Watch);

    $user->raiseSuspicion(Signal::soft('test', 30, decay: 'none')); // 55
    expect($user->fresh()->trustState())->toBe(TrustState::Restricted);

    $user->raiseSuspicion(Signal::soft('test', 30, decay: 'none')); // 85
    expect($user->fresh()->trustState())->toBe(TrustState::Review);
});

it('never bans on accumulated soft points alone (clamped to soft_max_state)', function (): void {
    $user = makeUser();

    // 500 points blows past the Banned threshold (120) — but it is soft.
    $user->raiseSuspicion(Signal::soft('test', 500, decay: 'none'));

    $fresh = $user->fresh();
    expect($fresh->trustState())->toBe(TrustState::Review)
        ->and($fresh->isBanned())->toBeFalse()
        ->and($fresh->suspicionScore())->toBe(500);
});

it('runs the restrict hook when entering Restricted', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('test', 55, decay: 'none'));

    expect($user->fresh()->restricted)->toBeTrue();
});
