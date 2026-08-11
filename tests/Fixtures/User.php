<?php

declare(strict_types=1);

namespace Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Guardian\Concerns\Guardable;
use Guardian\Enums\TrustState;

class User extends Model
{
    use Guardable;

    protected $guarded = [];

    protected $casts = [
        'banned' => 'boolean',
        'restricted' => 'boolean',
    ];

    public function guardianRestrict(TrustState $state, array $context = []): void
    {
        $this->forceFill(['restricted' => true])->save();
    }

    public function guardianBan(array $context = []): void
    {
        $this->forceFill(['banned' => true])->save();
    }
}
