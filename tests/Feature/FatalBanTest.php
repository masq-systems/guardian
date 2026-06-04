<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\Events\SubjectBanned;
use Masq\Guardian\Models\ModeratorReview;
use Masq\Guardian\ValueObjects\Signal;

it('bans immediately on a fatal hard signal', function (): void {
    Event::fake([SubjectBanned::class]);
    $user = makeUser();

    $user->raiseSuspicion(Signal::fatal('clock_skew', ['date' => '2099-01-01'], 'impossible date'));

    $fresh = $user->fresh();
    expect($fresh->trustState())->toBe(TrustState::Banned)
        ->and($fresh->isBanned())->toBeTrue()
        ->and($fresh->banned)->toBeTrue()
        ->and($fresh->trustProfiles()->where('track', 'default')->first()->banned_at)->not->toBeNull();

    Event::assertDispatched(SubjectBanned::class);
});

it('also opens a moderation case when banning', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::fatal('emulator', ['density' => 9001]));

    expect(ModeratorReview::query()->where('status', ModeratorReview::STATUS_PENDING)->count())->toBe(1);
});

it('keeps a ban permanent even after the score decays', function (): void {
    $user = makeUser();
    $user->raiseSuspicion(Signal::fatal('emulator'));

    $this->travel(60)->days();
    $user->raiseSuspicion(Signal::soft('noise', 1)); // triggers re-evaluation

    expect($user->fresh()->trustState())->toBe(TrustState::Banned);
});
