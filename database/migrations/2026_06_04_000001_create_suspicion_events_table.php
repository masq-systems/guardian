<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('guardian.tables.events', 'suspicion_events'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->string('track', 64)->default('default')->index();
            $table->string('detector')->index();
            $table->integer('points');
            $table->string('severity', 16)->default('soft');
            $table->boolean('fatal')->default(false);
            $table->string('decay', 32)->nullable();
            $table->json('evidence')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'track', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('guardian.tables.events', 'suspicion_events'));
    }
};
