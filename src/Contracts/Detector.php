<?php

declare(strict_types=1);

namespace Guardian\Contracts;

use Guardian\ValueObjects\Signal;

interface Detector
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return Signal|list<Signal>|null
     */
    public function inspect(object $subject, array $context = []): Signal|array|null;
}
