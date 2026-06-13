<?php

declare(strict_types=1);

namespace Masq\Guardian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Masq\Guardian\Models\SuspicionEvent;

class SuspicionRaised
{
    use Dispatchable;

    public function __construct(
        public readonly object $subject,
        public readonly SuspicionEvent $event,
    ) {}
}
