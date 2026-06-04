<?php

declare(strict_types=1);

namespace Masq\Guardian\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired when a subject is automatically banned by a fatal hard signal. */
class SubjectBanned
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly object $subject,
        public readonly int $score,
        public readonly array $context = [],
    ) {}
}
