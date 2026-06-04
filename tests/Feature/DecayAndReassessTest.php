<?php

declare(strict_types=1);

use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\Jobs\ReevaluateTrust;
use Masq\Guardian\ValueObjects\Signal;

it('lowers the score and state as linear-decay points fade', function (): void {
    $user = makeUser();

    // 100 points, linear over 30 days -> Review (>=80).
    $user->raiseSuspicion(Signal::soft('test', 100, decay: 'linear'));
    expect($user->fresh()->trustState())->toBe(TrustState::Review);

    // Half-way: ~50 points -> Restricted (>=50).
    $this->travel(15)->days();
    Guardian::reassess($user);
    expect($user->fresh()->trustState())->toBe(TrustState::Restricted);

    // Fully decayed: 0 points -> Trusted.
    $this->travel(16)->days();
    Guardian::reassess($user);
    $fresh = $user->fresh();
    expect($fresh->suspicionScore())->toBe(0)
        ->and($fresh->trustState())->toBe(TrustState::Trusted);
});

it('reassesses flagged subjects via the scheduled job', function (): void {
    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('test', 100, decay: 'linear'));

    $this->travel(40)->days();
    (new ReevaluateTrust)->handle();

    expect($user->fresh()->trustState())->toBe(TrustState::Trusted);
});
