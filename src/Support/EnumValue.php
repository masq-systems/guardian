<?php

declare(strict_types=1);

namespace Masq\Guardian\Support;

use BackedEnum;
use UnitEnum;

/**
 * Normalises a "string or enum" input to a plain string:
 * a backed enum yields its value, a pure enum its name, a string is unchanged.
 */
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
