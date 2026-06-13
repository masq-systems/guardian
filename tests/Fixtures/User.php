<?php

declare(strict_types=1);

namespace Masq\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Masq\Guardian\Concerns\Guardable;
use Masq\Guardian\Enums\TrustState;

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
