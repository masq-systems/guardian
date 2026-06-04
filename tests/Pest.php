<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Masq\Guardian\Tests\Fixtures\User;
use Masq\Guardian\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/** Create a fresh suspectable user fixture. */
function makeUser(string $name = 'Tester'): User
{
    return User::create(['name' => $name]);
}
