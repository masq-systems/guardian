<?php

declare(strict_types=1);

namespace Guardian\Actions;

use Guardian\Contracts\Action;
use Guardian\Contracts\TrustStateContract;
use Guardian\Events\SentToReview;
use Guardian\Models\ModeratorReview;
use Guardian\Models\SuspicionEvent;

final class QueueForReviewAction implements Action
{
    /**
     * @param  array<string, mixed>  $context
     */
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
