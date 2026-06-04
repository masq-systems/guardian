<?php

declare(strict_types=1);

namespace Masq\Guardian\Actions;

use Masq\Guardian\Contracts\Action;
use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\Events\SentToReview;
use Masq\Guardian\Models\ModeratorReview;
use Masq\Guardian\Models\SuspicionEvent;

/**
 * Open a pending moderation case (deduplicated) snapshotting the current
 * score and the evidence that led here.
 */
final class QueueForReviewAction implements Action
{
    public function handle(object $subject, TrustStateContract $state, array $context = []): void
    {
        $track = is_string($context['track'] ?? null) ? $context['track'] : 'default';

        $alreadyPending = $subject->moderatorReviews()
            ->where('track', $track)
            ->where('status', ModeratorReview::STATUS_PENDING)
            ->exists();

        if ($alreadyPending) {
            return;
        }

        /** @var array<int, array<string, mixed>> $recent */
        $recent = $subject->suspicionEvents()
            ->where('track', $track)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SuspicionEvent $e): array => [
                'detector' => $e->detector,
                'points' => $e->points,
                'severity' => $e->severity->value,
                'reason' => $e->reason,
                'evidence' => $e->evidence,
                'at' => $e->created_at->toIso8601String(),
            ])
            ->all();

        /** @var ModeratorReview $review */
        $review = $subject->moderatorReviews()->create([
            'track' => $track,
            'status' => ModeratorReview::STATUS_PENDING,
            'reason' => "Reached state: {$state->key()}",
            'score_at_flag' => $subject->suspicionScore($track),
            'evidence' => ['state' => $state->key(), 'signals' => $recent, 'context' => $context],
        ]);

        SentToReview::dispatch($subject, $review);
    }
}
