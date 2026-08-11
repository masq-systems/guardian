<?php

declare(strict_types=1);

namespace Guardian\Actions;

use Guardian\Contracts\Action;
use Guardian\Contracts\TrustStateContract;
use Guardian\Events\SubjectBanned;
use Guardian\Support\States;

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
