<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('guardian.tables.reviews', 'moderator_reviews'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->string('track', 64)->default('default')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->string('reason')->nullable();
            $table->integer('score_at_flag')->default(0);
            $table->json('evidence')->nullable();
            $table->string('decided_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('guardian.tables.reviews', 'moderator_reviews'));
    }
};
