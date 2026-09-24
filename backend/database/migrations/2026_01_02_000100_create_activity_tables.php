<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walking_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('device_id')->constrained();
            $table->uuid('client_session_id');
            $table->unsignedBigInteger('sequence');
            $table->char('payload_hash', 64);
            $table->string('kind', 16);
            $table->string('source', 24)->default('step_counter');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->date('local_date');
            $table->unsignedInteger('raw_steps');
            $table->unsignedInteger('verified_steps')->nullable();
            $table->unsignedInteger('distance_m')->default(0);
            $table->unsignedInteger('duration_s');
            $table->unsignedInteger('active_duration_s')->default(0);
            $table->decimal('calories_kcal', 7, 1)->default(0);
            $table->string('activity_type', 16)->default('unknown');
            $table->json('gps_summary')->nullable();
            $table->json('motion_summary')->nullable();
            $table->unsignedInteger('overlap_s')->default(0);
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->unsignedTinyInteger('fraud_score')->nullable()->index();
            $table->unsignedInteger('rule_set_version')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->string('reward_status', 32)->default('none');
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'client_session_id']);
            $table->unique(['device_id', 'sequence']);
            $table->index(['user_id', 'local_date']);
            $table->index(['user_id', 'started_at']);
            $table->index(['status', 'created_at']);
        });

        // Coarse samples: one-minute buckets for active walks, background windows
        // (≤ 60 min) for passive tracking. Pruned after the retention window.
        Schema::create('activity_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walking_session_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->unsignedSmallInteger('duration_s');
            $table->unsignedSmallInteger('steps');
            $table->unsignedSmallInteger('detector_steps')->nullable();
            $table->decimal('accel_std', 6, 3)->nullable();
            $table->decimal('accel_peak_hz', 4, 2)->nullable();
            $table->string('activity_type', 16)->nullable();
            $table->unsignedTinyInteger('activity_confidence')->nullable();
            $table->decimal('speed_mps', 5, 2)->nullable();
            $table->unsignedSmallInteger('gps_accuracy_m')->nullable();
            $table->unique(['walking_session_id', 'started_at']);
            $table->index('started_at');
        });

        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->date('local_date');
            $table->unsignedInteger('raw_steps')->default(0);
            $table->unsignedInteger('verified_steps')->default(0);
            $table->unsignedInteger('distance_m')->default(0);
            $table->decimal('calories_kcal', 8, 1)->default(0);
            $table->unsignedSmallInteger('active_minutes')->default(0);
            $table->unsignedInteger('goal_steps');
            $table->timestamp('goal_reached_at')->nullable();
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('rewarded_steps')->default(0);
            $table->unsignedSmallInteger('sessions_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'local_date']);
            $table->index(['local_date', 'verified_steps']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 48);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->json('properties')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['name', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('daily_activities');
        Schema::dropIfExists('activity_samples');
        Schema::dropIfExists('walking_sessions');
    }
};
