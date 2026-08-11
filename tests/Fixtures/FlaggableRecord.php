<?php

declare(strict_types=1);

namespace Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Guardian\Concerns\Flaggable;

class FlaggableRecord extends Model
{
    use Flaggable;

    protected $table = 'users';

    protected $guarded = [];
}
