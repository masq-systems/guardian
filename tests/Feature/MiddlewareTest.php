<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Masq\Guardian\Facades\Guardian;
use Masq\Guardian\Http\Middleware\EnforceTrust;
use Masq\Guardian\ValueObjects\Signal;
use Symfony\Component\HttpKernel\Exception\HttpException;

function runMiddleware(object $user, string $state = 'banned', ?string $track = null): string
{
    $request = Request::create('/x');
    $request->setUserResolver(fn () => $user);

    $response = (new EnforceTrust)->handle($request, fn () => response('ok'), $state, $track);

    return $response->getContent();
}

it('passes a trusted subject', function (): void {
    expect(runMiddleware(makeUser()))->toBe('ok');
});

it('blocks a banned subject', function (): void {
    $user = makeUser();
    Guardian::ban($user);

    expect(fn () => runMiddleware($user))->toThrow(HttpException::class);
});

it('blocks at a configurable state threshold', function (): void {
    $user = makeUser();
    $user->raiseSuspicion(Signal::soft('t', 90, decay: 'none'));

    expect(fn () => runMiddleware($user, 'review'))->toThrow(HttpException::class);

    expect(runMiddleware($user, 'banned'))->toBe('ok');
});

it('tracks the check', function (): void {
    $user = makeUser();
    Guardian::track('behavior')->ban($user);

    expect(fn () => runMiddleware($user, 'banned', 'behavior'))->toThrow(HttpException::class);
    expect(runMiddleware($user, 'banned'))->toBe('ok');
});
