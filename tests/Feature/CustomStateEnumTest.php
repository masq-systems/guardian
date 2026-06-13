<?php

declare(strict_types=1);

use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\ValueObjects\Signal;

enum FineState: string implements TrustStateContract
{
    case Ok = 'ok';
    case Watch = 'watch';
    case Probation = 'probation';
    case Review = 'review';
    case Gone = 'gone';

    public function key(): string
    {
        return $this->value;
    }

    public function level(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Watch => 1,
            self::Probation => 2,
            self::Review => 3,
            self::Gone => 4,
        };
    }

    public static function base(): self
    {
        return self::Ok;
    }

    public static function terminal(): self
    {
        return self::Gone;
    }

    public static function fromKey(string $key): self
    {
        return self::from($key);
    }

    public static function tryFromKey(?string $key): ?self
    {
        return $key === null ? null : self::tryFrom($key);
    }

    public static function all(): array
    {
        return self::cases();
    }
}

beforeEach(function (): void {

    config()->set('guardian.state_enum', FineState::class);
    config()->set('guardian.tracks.default', [
        'thresholds' => [
            0 => FineState::Ok,
            20 => FineState::Watch,
            50 => FineState::Probation,
            80 => FineState::Review,
            120 => FineState::Gone,
        ],
        'soft_max_state' => FineState::Review,
        'actions' => [],
        'detectors' => [],
    ]);
});

it('uses the custom enum and reaches the extra step', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('x', 55, decay: 'none'));

    expect($user->fresh()->trustState())->toBe(FineState::Probation)
        ->and($user->fresh()->trustState()->key())->toBe('probation');
});

it('still clamps soft points to the custom soft_max_state', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::soft('x', 500, decay: 'none'));

    expect($user->fresh()->trustState())->toBe(FineState::Review)
        ->and($user->fresh()->isBanned())->toBeFalse();
});

it('bans to the custom terminal state on a fatal signal', function (): void {
    $user = makeUser();

    $user->raiseSuspicion(Signal::fatal('x'));

    expect($user->fresh()->trustState())->toBe(FineState::Gone)
        ->and($user->fresh()->isBanned())->toBeTrue();
});
