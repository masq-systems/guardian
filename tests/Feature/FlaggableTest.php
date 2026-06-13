<?php

declare(strict_types=1);

use Masq\Guardian\Models\GuardianFlag;
use Masq\Guardian\Tests\Fixtures\FlaggableRecord;

it('marks a record invalid and excludes it from the valid scope', function (): void {
    $a = FlaggableRecord::create(['name' => 'a']);
    $b = FlaggableRecord::create(['name' => 'b']);

    $a->flagAsInvalid(reason: 'slice_density');

    expect($a->isGuardianFlagged())->toBeTrue()
        ->and($b->isGuardianFlagged())->toBeFalse()
        ->and(FlaggableRecord::guardianValid()->orderBy('id')->pluck('id')->all())->toBe([$b->id])
        ->and(FlaggableRecord::guardianFlagged()->orderBy('id')->pluck('id')->all())->toBe([$a->id]);
});

it('is idempotent: re-flagging keeps a single auto-flag row', function (): void {
    $a = FlaggableRecord::create(['name' => 'a']);

    $a->flagAsInvalid(reason: 'x');
    $a->flagAsInvalid(reason: 'y');

    expect($a->guardianFlags()->count())->toBe(1)
        ->and($a->guardianFlags()->first()->reason)->toBe('y');
});

it('clears auto-flags but keeps moderator decisions', function (): void {
    $a = FlaggableRecord::create(['name' => 'a']);

    $a->flagAsInvalid(reason: 'auto');
    $a->clearAutoFlags();
    expect($a->isGuardianFlagged())->toBeFalse();

    // A moderator-confirmed flag must survive clearAutoFlags().
    $a->guardianFlags()->create([
        'track' => 'default',
        'state' => GuardianFlag::STATE_CONFIRMED,
        'severity' => 'hard',
    ]);
    $a->clearAutoFlags();
    expect($a->isGuardianFlagged())->toBeTrue();
});

it('keeps tracks independent', function (): void {
    $a = FlaggableRecord::create(['name' => 'a']);

    $a->flagAsInvalid(track: 'behavior');

    expect($a->isGuardianFlagged('behavior'))->toBeTrue()
        ->and($a->isGuardianFlagged('default'))->toBeFalse()
        ->and(FlaggableRecord::guardianValid('default')->count())->toBe(1)
        ->and(FlaggableRecord::guardianValid('behavior')->count())->toBe(0);
});
