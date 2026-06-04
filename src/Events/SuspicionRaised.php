<?php

declare(strict_types=1);

namespace Masq\Guardian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Masq\Guardian\Models\SuspicionEvent;

/** Fired for every suspicion event persisted. */
class SuspicionRaised
{
    use Dispatchable;

    public function __construct(
        public readonly object $subject,
        public readonly SuspicionEvent $event,
    ) {}
}
