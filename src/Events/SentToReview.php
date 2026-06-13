<?php

declare(strict_types=1);

namespace Masq\Guardian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Masq\Guardian\Models\ModeratorReview;

class SentToReview
{
    use Dispatchable;

    public function __construct(
        public readonly object $subject,
        public readonly ModeratorReview $review,
    ) {}
}
