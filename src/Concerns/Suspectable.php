<?php

declare(strict_types=1);

namespace Masq\Guardian\Concerns;

/**
 * @deprecated Use {@see Guardable} instead. Kept as a backwards-compatible alias.
 */
trait Suspectable
{
    use Guardable;
}
