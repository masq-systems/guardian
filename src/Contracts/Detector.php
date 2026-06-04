<?php

declare(strict_types=1);

namespace Masq\Guardian\Contracts;

use Masq\Guardian\ValueObjects\Signal;

interface Detector
{
    /** Stable identifier stored on every event this detector raises. */
    public function key(): string;

    /**
     * Inspect a subject and optionally raise a suspicion Signal.
     *
     * @param  object  $subject  the model being evaluated (uses Guardable)
     * @param  array<string, mixed>  $context  arbitrary request/job payload
     * @return Signal|array<int, Signal>|null
     */
    public function inspect(object $subject, array $context = []): Signal|array|null;
}
