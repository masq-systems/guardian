<?php

declare(strict_types=1);

namespace Masq\Guardian\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Penalized = 'penalized';
    case Banned = 'banned';
}
