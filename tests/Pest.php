<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Guardian\Tests\Fixtures\User;
use Guardian\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function makeUser(string $name = 'Tester'): User
{
    return User::create(['name' => $name]);
}
