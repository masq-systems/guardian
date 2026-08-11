<?php

declare(strict_types=1);

namespace Guardian\Support;

use BackedEnum;
use UnitEnum;

final class EnumValue
{
    public static function toString(string|BackedEnum|UnitEnum|null $value): ?string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            default => $value,
        };
    }
}
