<?php

declare(strict_types=1);

namespace Guardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Guardian\Support\States;
use Symfony\Component\HttpFoundation\Response;

final class EnforceTrust
{
    public function handle(Request $request, Closure $next, string $state = 'banned', ?string $track = null): Response
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'trustState')) {
            $states = app(States::class);
            $block = $states->tryFromKey($state) ?? $states->terminal();

            if ($user->trustState($track)->level() >= $block->level()) {
                abort(403, 'Access blocked by Guardian trust state.');
            }
        }

        return $next($request);
    }
}
