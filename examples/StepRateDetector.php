<?php

declare(strict_types=1);

// EXAMPLE — copy into the host app (e.g. app/Guardian/Detectors) and register
// it in config/guardian.php. This file is not autoloaded by the package.
//
//   'detectors' => [
//       'step_rate' => [
//           'class' => App\Guardian\Detectors\StepRateDetector::class,
//           'max_per_minute' => 300,
//           'slice_minutes'  => 15,
//       ],
//   ],

namespace App\Guardian\Detectors;

use Masq\Guardian\Detectors\AbstractDetector;
use Masq\Guardian\ValueObjects\Signal;

/**
 * Road Trip Runner anti-cheat: inspect a day's step slices.
 *
 * Context shape: ['slices' => ['HH:MM' => <absolute int>, ...]].
 *
 *  - steps/minute beyond a human maximum  -> hard (escalates to review)
 *  - perfectly monotonous slices (bot)    -> soft points (accumulate)
 *
 * @return Signal|array<int, Signal>|null
 */
final class StepRateDetector extends AbstractDetector
{
    public function inspect(object $subject, array $context = []): Signal|array|null
    {
        $slices = $context['slices'] ?? [];

        if ($slices === []) {
            return null;
        }

        $maxPerMinute = (int) $this->option('max_per_minute', 300);
        $sliceMinutes = (int) $this->option('slice_minutes', 15);
        $maxPerSlice = $maxPerMinute * $sliceMinutes;

        $signals = [];
        $values = array_values($slices);

        // 1. Physically impossible cadence -> hard. Flip to Signal::fatal() to
        //    auto-ban instead of routing to a moderator.
        $peak = max($values);
        if ($peak > $maxPerSlice) {
            $signals[] = Signal::hard(
                $this->key(),
                60,
                ['peak_slice' => $peak, 'limit' => $maxPerSlice],
                'Steps per minute exceeds human maximum',
            );
        }

        // 2. Robotic monotony: many identical non-zero slices -> soft.
        $nonZero = array_filter($values, fn (int $v): bool => $v > 0);
        if (count($nonZero) >= 6 && count(array_unique($nonZero)) === 1) {
            $signals[] = Signal::soft(
                $this->key().'_monotony',
                20,
                ['repeated_value' => reset($nonZero), 'count' => count($nonZero)],
                'Identical step values across many slices',
            );
        }

        return $signals;
    }
}
