<?php

declare(strict_types=1);

namespace Masq\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Masq\Guardian\Concerns\Flaggable;

class FlaggableRecord extends Model
{
    use Flaggable;

    protected $table = 'users';

    protected $guarded = [];
}
