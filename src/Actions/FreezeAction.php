<?php

declare(strict_types=1);

namespace Masq\Guardian\Actions;

use Masq\Guardian\Contracts\Action;
use Masq\Guardian\Contracts\TrustStateContract;

/**
 * Soft restriction. Delegates to the subject's optional `guardianRestrict()`
 * method (e.g. freeze rewards / progress) so the host app owns the meaning.
 */
final class FreezeAction implements Action
{
    public function handle(object $subject, TrustStateContract $state, array $context = []): void
    {
        if (method_exists($subject, 'guardianRestrict')) {
            $subject->guardianRestrict($state, $context);
        }
    }
}
