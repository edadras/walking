<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->unsignedSmallInteger('amount_ml');
            $table->timestamp('logged_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'local_date']);
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('level')->unique();
            $table->unsignedBigInteger('min_xp')->index();
            $table->string('title', 64);
        });

        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('amount');
            $table->string('reason', 32);
            $table->string('idempotency_key', 128);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('description');
            $table->string('icon', 32)->default('medal');
            $table->string('metric', 32)->index();
            $table->unsignedBigInteger('threshold');
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('point_reward')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::create('user_streaks', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_days')->default(0);
            $table->unsignedSmallInteger('longest_days')->default(0);
            $table->date('last_goal_date')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 32);
            $table->unsignedBigInteger('value');
            $table->date('local_date')->nullable();
            $table->timestamp('achieved_at');
            $table->unique(['user_id', 'metric']);
        });

        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('type', 16);
            $table->string('metric', 16);
            $table->unsignedBigInteger('target_value');
            $table->unsignedBigInteger('sponsor_id')->nullable()->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->unsignedInteger('reward_points')->default(0);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('max_participants')->nullable();
            $table->unsignedInteger('participants_count')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('join_until')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('created_by_type', 32)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('progress')->default(0);
            $table->timestamp('joined_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->unique(['challenge_id', 'user_id']);
            $table->index(['challenge_id', 'progress']);
            $table->index(['user_id', 'completed_at']);
        });

        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('board', 16);
            $table->string('period', 8);
            $table->string('period_key', 16);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->unsignedBigInteger('score');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['board', 'period', 'period_key', 'user_id']);
            $table->index(['board', 'period', 'period_key', 'rank']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users');
            $table->foreignId('referee_id')->unique()->constrained('users');
            $table->string('status', 16)->default('pending');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['referrer_id', 'status']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('deep_link')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'referrals', 'leaderboard_snapshots', 'challenge_participants', 'challenges', 'personal_records', 'user_streaks', 'user_achievements', 'achievements', 'xp_transactions', 'levels', 'water_logs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
