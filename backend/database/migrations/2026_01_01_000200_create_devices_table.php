<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->uuid('install_id')->unique();
            $table->string('platform', 16);
            $table->string('os_version', 32)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('manufacturer', 64)->nullable();
            $table->text('public_key');
            $table->char('public_key_fingerprint', 64)->unique();
            $table->boolean('key_attested')->default(false);
            $table->string('integrity_verdict', 32)->nullable();
            $table->timestamp('integrity_checked_at')->nullable();
            $table->boolean('emulator_suspected')->default(false);
            $table->boolean('root_suspected')->default(false);
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->string('status', 32)->default('active')->index();
            $table->string('push_provider', 16)->nullable();
            $table->string('push_token')->nullable();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->char('last_ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('device_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unique(['device_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->char('code_hash', 64);
            $table->string('purpose', 16)->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('device_user_links');
        Schema::dropIfExists('devices');
    }
};
