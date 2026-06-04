<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('guardian.tables.profiles', 'trust_profiles'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->string('track', 64)->default('default');
            $table->integer('score')->default(0);
            $table->string('state', 16)->default('trusted')->index();
            $table->timestamp('flagged_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'track']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('guardian.tables.profiles', 'trust_profiles'));
    }
};
