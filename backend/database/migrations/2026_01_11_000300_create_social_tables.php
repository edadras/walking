<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per pair (user_low < user_high); accepting is the consent to share weekly steps.
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 12); // pending | accepted
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_low_id', 'user_high_id']);
            $table->index(['user_high_id', 'status']);
        });

        // Friendly step races between friends: social only, no points (nothing to farm).
        Schema::create('friend_challenges', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->index(['creator_id', 'ends_on']);
        });

        Schema::create('friend_challenge_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('friend_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12); // invited | joined | left
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['friend_challenge_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friend_challenge_members');
        Schema::dropIfExists('friend_challenges');
        Schema::dropIfExists('friendships');
    }
};
