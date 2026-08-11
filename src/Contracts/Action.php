<?php

declare(strict_types=1);

namespace Guardian\Contracts;

interface Action
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(object $subject, TrustStateContract $state, array $context = []): void;
}
