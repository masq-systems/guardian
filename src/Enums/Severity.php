<?php

declare(strict_types=1);

namespace Masq\Guardian\Enums;

enum Severity: string
{
    /** Heuristic signal. Accumulates points but can never, alone, cause a permanent ban. */
    case Soft = 'soft';

    /** Physically-impossible / certain violation. May carry a fatal flag for instant ban. */
    case Hard = 'hard';
}
