<?php

declare(strict_types=1);

namespace Masq\Guardian\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Masq\Guardian\Concerns\Guardable;
use Masq\Guardian\Enums\TrustState;

/**
 * Test subject. Demonstrates the optional host-app hooks Guardian calls:
 * guardianRestrict() (FreezeAction) and guardianBan() (BanAction).
 */
class User extends Model
{
    use Guardable;

    protected $guarded = [];

    protected $casts = [
        'banned' => 'boolean',
        'restricted' => 'boolean',
    ];

    /** @param array<string, mixed> $context */
    public function guardianRestrict(TrustState $state, array $context = []): void
    {
        $this->forceFill(['restricted' => true])->save();
    }

    /** @param array<string, mixed> $context */
    public function guardianBan(array $context = []): void
    {
        $this->forceFill(['banned' => true])->save();
    }
}
