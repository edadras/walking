<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-defined daily/weekly missions; progress is computed from verified data.
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('title', 80);
            $table->string('description', 200)->nullable();
            $table->string('period', 8); // daily | weekly
            $table->string('metric', 24);
            $table->unsignedInteger('target');
            $table->unsignedInteger('reward_points')->default(0);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // One row per claimed quest per period: the unique key is the double-claim guard.
        Schema::create('quest_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 16);
            $table->unsignedInteger('progress');
            $table->foreignId('point_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('claimed_at');
            $table->unique(['user_id', 'quest_id', 'period_key']);
            $table->index(['quest_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_claims');
        Schema::dropIfExists('quests');
    }
};
