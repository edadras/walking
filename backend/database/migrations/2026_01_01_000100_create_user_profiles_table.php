<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('gender', 16)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->unsignedInteger('daily_step_goal');
            $table->unsignedInteger('water_goal_ml');
            $table->boolean('water_reminder_enabled')->default(false);
            $table->unsignedSmallInteger('water_reminder_interval_min')->default(120);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->boolean('push_enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_for');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_profiles');
    }
};
