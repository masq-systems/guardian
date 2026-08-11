<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Guardian\Contracts\Detector;
use Guardian\Enums\TrustState;
use Guardian\Events\SentToReview;
use Guardian\Events\SuspicionRaised;
use Guardian\Events\ThresholdCrossed;
use Guardian\Facades\Guardian;
use Guardian\ValueObjects\Signal;

it('dispatches events as suspicion is raised and thresholds cross', function (): void {
    Event::fake([SuspicionRaised::class, ThresholdCrossed::class, SentToReview::class]);
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('test', 85, decay: 'none'));

    Event::assertDispatched(SuspicionRaised::class);
    Event::assertDispatched(ThresholdCrossed::class, fn (ThresholdCrossed $e): bool => $e->to === TrustState::Review && $e->escalated());
    Event::assertDispatched(SentToReview::class);
});

it('runs registered detectors through inspect()', function (): void {
    $user = makeUser();

    $detector = new class implements Detector
    {
        public function key(): string
        {
            return 'always';
        }

        public function inspect(object $subject, array $context = []): Signal
        {
            return Signal::soft($this->key(), 60, decay: 'none');
        }
    };

    Guardian::register($detector);
    Guardian::inspect($user);

    expect($user->fresh()->trustState())->toBe(TrustState::Restricted);
});

it('deduplicates pending moderation cases', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('test', 85, decay: 'none'));
    $user->raiseSuspicion(Signal::soft('test', 5, decay: 'none'));

    expect($user->moderatorReviews()->where('status', 'pending')->count())->toBe(1);
});
