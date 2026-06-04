<?php

declare(strict_types=1);

namespace Masq\Guardian\Contracts;

interface Action
{
    /**
     * Run when a subject transitions into a state this action is bound to.
     *
     * @param  object  $subject  the model that transitioned
     * @param  TrustStateContract  $state  the state just entered
     * @param  array<string, mixed>  $context  evaluation context
     */
    public function handle(object $subject, TrustStateContract $state, array $context = []): void;
}
