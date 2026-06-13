<?php

declare(strict_types=1);

namespace Masq\Guardian\Contracts;

interface Action
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(object $subject, TrustStateContract $state, array $context = []): void;
}
