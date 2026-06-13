<?php

declare(strict_types=1);

namespace Masq\Guardian\Actions;

use Masq\Guardian\Contracts\Action;
use Masq\Guardian\Contracts\TrustStateContract;

final class FreezeAction implements Action
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(object $subject, TrustStateContract $state, array $context = []): void
    {
        if (method_exists($subject, 'guardianRestrict')) {
            $subject->guardianRestrict($state, $context);
        }
    }
}
