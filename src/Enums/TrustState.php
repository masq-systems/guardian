<?php

declare(strict_types=1);

namespace Masq\Guardian\Enums;

use Masq\Guardian\Contracts\TrustStateContract;

enum TrustState: string implements TrustStateContract
{
    case Trusted = 'trusted';
    case Watch = 'watch';
    case Restricted = 'restricted';
    case Review = 'review';
    case Banned = 'banned';

    public function key(): string
    {
        return $this->value;
    }

    /** Ordinal severity used for comparing / clamping states. */
    public function level(): int
    {
        return match ($this) {
            self::Trusted => 0,
            self::Watch => 1,
            self::Restricted => 2,
            self::Review => 3,
            self::Banned => 4,
        };
    }

    public static function base(): self
    {
        return self::Trusted;
    }

    public static function terminal(): self
    {
        return self::Banned;
    }

    public static function fromKey(string $key): self
    {
        return self::from($key);
    }

    public static function tryFromKey(?string $key): ?self
    {
        return $key === null ? null : self::tryFrom($key);
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::cases();
    }

    public function isWorseThan(TrustStateContract $other): bool
    {
        return $this->level() > $other->level();
    }

    public function atMost(TrustStateContract $ceiling): self
    {
        return $this->level() > $ceiling->level() ? self::fromKey($ceiling->key()) : $this;
    }
}
