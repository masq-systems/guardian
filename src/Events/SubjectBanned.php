<?php

declare(strict_types=1);

namespace Guardian\Events;

use Illuminate\Foundation\Events\Dispatchable;

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
