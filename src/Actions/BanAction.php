<?php

declare(strict_types=1);

namespace Masq\Guardian\Actions;

use Masq\Guardian\Contracts\Action;
use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\Events\SubjectBanned;
use Masq\Guardian\Support\States;

final class BanAction implements Action
{
    /**
     * @param  array<string, mixed>  $context
     */
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
