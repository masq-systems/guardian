<?php

declare(strict_types=1);

use Masq\Guardian\Actions\FreezeAction;
use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\ValueObjects\Signal;

it('accepts actions keyed by the state value', function (): void {
    config()->set('guardian.tracks.default.actions', [
        'restricted' => [FreezeAction::class],
    ]);

    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('t', 55, decay: 'none'));

    expect($user->fresh()->restricted)->toBeTrue();
});

it('accepts actions keyed by the state name', function (): void {
    config()->set('guardian.tracks.default.actions', [
        'Restricted' => [FreezeAction::class],
    ]);

    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('t', 55, decay: 'none'));

    expect($user->fresh()->restricted)->toBeTrue();
});

it('accepts the list form with an enum case', function (): void {
    config()->set('guardian.tracks.default.actions', [
        ['state' => TrustState::Restricted, 'actions' => [FreezeAction::class]],
    ]);

    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('t', 55, decay: 'none'));

    expect($user->fresh()->restricted)->toBeTrue();
});
