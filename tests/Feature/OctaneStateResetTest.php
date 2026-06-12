<?php

declare(strict_types=1);

use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Guardian;
use Masq\Guardian\Registry\TrackManager;
use Masq\Guardian\ValueObjects\Signal;

/**
 * Under Octane the Guardian/TrackManager singletons live for the whole worker,
 * so per-request state must be cleared between requests. These tests exercise
 * the reset methods the RequestReceived listener calls (the listener itself is
 * only wired when laravel/octane is installed).
 */

it('flushRequestState() drops ad-hoc detectors so they do not bleed into the next request', function (): void {
    $detector = new class implements Detector
    {
        public function key(): string
        {
            return 'ephemeral';
        }

        public function inspect(object $subject, array $context = []): ?Signal
        {
            return null;
        }
    };

    $guardian = app(Guardian::class);
    $guardian->register($detector);
    expect($guardian->detectors())->toContain($detector);

    // Simulate the start of the next request on the same worker.
    $guardian->flushRequestState();

    expect($guardian->detectors())->not->toContain($detector);
});

it('TrackManager::flush() discards runtime registry mutations and rebuilds from config', function (): void {
    $tracks = app(TrackManager::class);
    $track = $tracks->defaultTrack();

    $keys = $tracks->registry($track)->keys();
    expect($keys)->toContain('throttle_hits');

    // Disable a detector at runtime on the memoised registry...
    $tracks->registry($track)->disable('throttle_hits');
    expect($tracks->registry($track)->keys())->not->toContain('throttle_hits');

    // ...then flush: the next request rebuilds tracks from the boot config.
    $tracks->flush();

    expect($tracks->registry($track)->keys())->toContain('throttle_hits');
});

it('Guardian and TrackManager are the same singleton instances across resolves', function (): void {
    expect(app(Guardian::class))->toBe(app(Guardian::class));
    expect(app(TrackManager::class))->toBe(app(TrackManager::class));
});
