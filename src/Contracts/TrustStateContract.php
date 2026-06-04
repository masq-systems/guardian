<?php

declare(strict_types=1);

namespace Masq\Guardian\Contracts;

/**
 * A trust state — one rung on the escalation ladder. The package ships
 * Masq\Guardian\Enums\TrustState, but an app can supply its OWN enum (with
 * extra rungs) by implementing this contract and pointing `state_enum` at it
 * in config/guardian.php.
 *
 * Implementations are almost always string-backed enums:
 *
 *   enum MyState: string implements TrustStateContract { case Trusted = 'trusted'; ... }
 */
interface TrustStateContract
{
    /** Stable string id, stored on the profile and used in config/middleware. */
    public function key(): string;

    /** Ordering — higher means less trusted. Comparisons use this. */
    public function level(): int;

    /** The "all good" baseline (lowest level). */
    public static function base(): self;

    /** The terminal state a ban resolves to (highest level). */
    public static function terminal(): self;

    public static function fromKey(string $key): self;

    public static function tryFromKey(?string $key): ?self;

    /** @return array<int, self> */
    public static function all(): array;
}
