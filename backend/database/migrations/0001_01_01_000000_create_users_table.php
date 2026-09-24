<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('phone', 20)->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('display_name', 50)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->string('status_reason')->nullable();
            $table->string('referral_code', 12)->unique();
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->timestamp('timezone_changed_at')->nullable();
            $table->string('locale', 8)->default('fa');
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->boolean('leaderboard_visible')->default(true);
            $table->timestamp('last_active_at')->nullable()->index();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
