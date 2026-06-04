<?php

declare(strict_types=1);

namespace Masq\Guardian\Detectors;

use Masq\Guardian\ValueObjects\Signal;

/**
 * Default, domain-agnostic detector: scores a subject for repeatedly hitting a
 * rate limit / throttle (e.g. brute-forcing login, hammering an endpoint).
 *
 * Driven by a rolling hit counter — increment it with
 * Guardian::recordThrottleHit($subject, $limiter), which feeds the current
 * count to this detector via $context['hits'].
 *
 * Options (config):
 *   allowed_hits          int   free hits before scoring starts           (default 5)
 *   base_points           int   points awarded once allowance is exceeded (default 12)
 *   points_per_extra_hit  int   added for each hit beyond the allowance    (default 6)
 *   max_points            int   cap on a single signal                     (default 100)
 *   decay                 string  decay strategy key                       (default 'half_life')
 */
final class ThrottleHitDetector extends AbstractDetector
{
    public function inspect(object $subject, array $context = []): ?Signal
    {
        $hitsRaw = $context['hits'] ?? 0;
        $hits = is_numeric($hitsRaw) ? (int) $hitsRaw : 0;
        $allowed = $this->intOption('allowed_hits', 5);

        if ($hits <= $allowed) {
            return null;
        }

        $base = $this->intOption('base_points', 12);
        $perExtra = $this->intOption('points_per_extra_hit', 6);
        $max = $this->intOption('max_points', 100);

        $points = min($max, $base + ($hits - $allowed - 1) * $perExtra);

        $limiter = $context['limiter'] ?? 'default';

        return Signal::soft(
            $this->key(),
            $points,
            [
                'hits' => $hits,
                'allowed_hits' => $allowed,
                'limiter' => is_string($limiter) ? $limiter : 'default',
            ],
            'Excessive throttle / rate-limit hits',
            $this->stringOption('decay', 'half_life'),
        );
    }
}
