<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('state', 16)->default('flagged');
            $table->json('evidence')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['flaggable_type', 'flaggable_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('guardian.tables.flags', 'guardian_flags'));
    }
};
