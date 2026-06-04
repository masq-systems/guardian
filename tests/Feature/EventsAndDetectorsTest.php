<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Enums\TrustState;
use Masq\Guardian\Events\SentToReview;
use Masq\Guardian\Events\SuspicionRaised;
use Masq\Guardian\Events\ThresholdCrossed;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\ValueObjects\Signal;

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
