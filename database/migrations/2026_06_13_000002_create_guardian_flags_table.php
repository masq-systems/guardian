<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic "this record is invalid" marks.
 *
 * Where `suspicion_events` score a *subject* (a user) over time, a flag marks a
 * single *record* (e.g. one day of activity) as not-to-be-counted. The record's
 * data is never deleted — only marked — and the mark is reversible (a moderator
 * can clear or confirm it). Any model can be flagged via the Flaggable trait,
 * with no per-table column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('guardian.tables.flags', 'guardian_flags'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('flaggable');
            $table->string('track', 64)->default('default')->index();
            $table->string('reason')->nullable();
            $table->string('severity', 16)->default('hard');
            // flagged = auto-raised (excluded); confirmed = moderator upheld
            // (excluded); cleared = moderator dismissed (counted again).
            $table->string('state', 16)->default('flagged');
            $table->json('evidence')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            // Hot path: "is this record (in)valid?" — anti-join / exists by
            // flaggable + state.
            $table->index(['flaggable_type', 'flaggable_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('guardian.tables.flags', 'guardian_flags'));
    }
};
