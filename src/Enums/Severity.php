<?php

declare(strict_types=1);

namespace Masq\Guardian\Enums;

enum Severity: string
{
    case Soft = 'soft';

    case Hard = 'hard';
}
