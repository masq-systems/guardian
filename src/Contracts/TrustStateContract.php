<?php

declare(strict_types=1);

namespace Masq\Guardian\Contracts;

interface TrustStateContract
{
    public function key(): string;

    public function level(): int;

    public static function base(): self;

    public static function terminal(): self;

    public static function fromKey(string $key): self;

    public static function tryFromKey(?string $key): ?self;

    /**
     * @return list<self>
     */
    public static function all(): array;
}
