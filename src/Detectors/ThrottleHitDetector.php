<?php

declare(strict_types=1);

namespace Masq\Guardian\Detectors;

use Masq\Guardian\ValueObjects\Signal;

final class ThrottleHitDetector extends AbstractDetector
{
    /**
     * @param  array<string, mixed>  $context
     */
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
