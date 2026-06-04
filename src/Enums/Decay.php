<?php

declare(strict_types=1);

namespace Masq\Guardian\Enums;

/**
 * Convenience enum for the built-in decay strategy keys. Pass a case anywhere
 * a decay key is accepted (e.g. Signal::soft(..., decay: Decay::HalfLife)).
 * Custom strategies added in config can still be referenced by string.
 */
enum Decay: string
{
    case None = 'none';
    case Linear = 'linear';
    case HalfLife = 'half_life';
}
