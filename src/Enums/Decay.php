<?php

declare(strict_types=1);

namespace Masq\Guardian\Enums;

enum Decay: string
{
    case None = 'none';
    case Linear = 'linear';
    case HalfLife = 'half_life';
}
