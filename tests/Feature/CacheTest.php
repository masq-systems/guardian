<?php

declare(strict_types=1);

use Masq\Guardian\Support\TrustCache;
use Masq\Guardian\ValueObjects\Signal;

it('serves trust standing from the cache, not the database', function (): void {
    $user = makeUser();
    $user->raiseSuspicion(Signal::fatal('emulator'));

    $cache = app(TrustCache::class);
    expect($cache->standing($user, 'default')['banned'])->toBeTrue();

    // Mutate the DB row directly behind the cache's back.
    $user->trustProfiles()->where('track', 'default')->update(['banned_at' => null, 'state' => 'trusted', 'score' => 0]);

    // Read still reflects the cached (banned) standing.
    expect($user->fresh()->isBanned())->toBeTrue();

    // After invalidation it re-warms from the database.
    $cache->forget($user, 'default');
    expect($user->fresh()->isBanned())->toBeFalse();
});

it('refreshes the cache on every evaluation', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('test', 25, decay: 'none'));
    expect(app(TrustCache::class)->standing($user, 'default')['state'])->toBe('watch');

    $user->raiseSuspicion(Signal::soft('test', 60, decay: 'none'));
    expect(app(TrustCache::class)->standing($user, 'default')['state'])->toBe('review');
});
