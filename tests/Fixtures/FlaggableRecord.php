<?php

declare(strict_types=1);

namespace Masq\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Masq\Guardian\Concerns\Flaggable;

/**
 * Test record that can be marked invalid. Reuses the fixture `users` table —
 * Flaggable is table-agnostic; we only need a model with an id.
 */
class FlaggableRecord extends Model
{
    use Flaggable;

    protected $table = 'users';

    protected $guarded = [];
}
