<?php

declare(strict_types=1);

namespace Masq\Guardian\Actions;

use Masq\Guardian\Contracts\Action;
use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\Events\SubjectBanned;
use Masq\Guardian\Support\States;

/**
 * Apply an automatic ban. Only ever runs for the terminal (banned) state, which
 * the engine reaches automatically only on a fatal hard signal. Calls the
 * configurable ban method on the subject if present, then fires SubjectBanned.
 */
final class BanAction implements Action
{
    public function handle(object $subject, TrustStateContract $state, array $context = []): void
    {
        if (! app(States::class)->isTerminal($state)) {
            return;
        }

        $track = is_string($context['track'] ?? null) ? $context['track'] : 'default';
        $method = config('guardian.ban_method', 'guardianBan');

        if (is_string($method) && method_exists($subject, $method)) {
            $subject->{$method}($context);
        }

        SubjectBanned::dispatch($subject, $subject->suspicionScore($track), $context);
    }
}
