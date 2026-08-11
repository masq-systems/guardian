<?php

declare(strict_types=1);

namespace Guardian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Guardian\Contracts\TrustStateContract;

class ThresholdCrossed
{
    use Dispatchable;

    public function __construct(
        public readonly object $subject,
        public readonly TrustStateContract $from,
        public readonly TrustStateContract $to,
        public readonly int $score,
        public readonly string $track = 'default',
    ) {}

    public function escalated(): bool
    {
        return $this->to->level() > $this->from->level();
    }
}
