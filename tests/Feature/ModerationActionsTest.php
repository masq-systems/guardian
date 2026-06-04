<?php

declare(strict_types=1);

use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\ValueObjects\Signal;

enum BanReason: string
{
    case Cheating = 'cheating_confirmed';
}

enum PlainReason
{
    case Abuse;
}

it('bans and unbans via the trait', function (): void {
    $user = makeUser();

    $user->ban('cheating confirmed');
    expect($user->fresh()->isBanned())->toBeTrue();

    $user->unban();
    expect($user->fresh()->isBanned())->toBeFalse();
});

it('manually bans a subject', function (): void {
    $user = makeUser();

    Guardian::ban($user, 'cheating confirmed');

    $fresh = $user->fresh();
    expect($fresh->isBanned())->toBeTrue()
        ->and($fresh->trustState())->toBe(TrustState::Banned)
        ->and($fresh->banned)->toBeTrue();
});

it('clears a flagged subject back to trusted', function (): void {
    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('test', 90, decay: 'none'));
    expect($user->fresh()->trustState())->toBe(TrustState::Review);

    Guardian::clear($user);

    $fresh = $user->fresh();
    expect($fresh->trustState())->toBe(TrustState::Trusted)
        ->and($fresh->suspicionScore())->toBe(0)
        ->and($fresh->suspicionEvents()->count())->toBe(0);
});

it('unbans by clearing a banned subject', function (): void {
    $user = makeUser();
    Guardian::ban($user);
    expect($user->fresh()->isBanned())->toBeTrue();

    Guardian::clear($user);
    expect($user->fresh()->isBanned())->toBeFalse();
});

it('accepts a backed enum as the ban reason and stores its value', function (): void {
    $user = makeUser();

    Guardian::ban($user, BanReason::Cheating);

    $event = $user->suspicionEvents()->where('detector', 'manual_ban')->sole();
    expect($event->reason)->toBe('cheating_confirmed');
});

it('accepts a pure enum as the ban reason and stores its name', function (): void {
    $user = makeUser();

    Guardian::ban($user, PlainReason::Abuse);

    $event = $user->suspicionEvents()->where('detector', 'manual_ban')->sole();
    expect($event->reason)->toBe('Abuse');
});
