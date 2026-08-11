<?php

declare(strict_types=1);

use Guardian\Enums\TrustState;
use Guardian\Facades\Guardian;
use Guardian\Jobs\ReevaluateTrust;
use Guardian\ValueObjects\Signal;

it('lowers the score and state as linear-decay points fade', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('test', 100, decay: 'linear'));
    expect($user->fresh()->trustState())->toBe(TrustState::Review);

    $this->travel(15)->days();
    Guardian::reassess($user);
    expect($user->fresh()->trustState())->toBe(TrustState::Restricted);

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
