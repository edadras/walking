<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('legal_name', 190)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            // Points the sponsor has paid for; rewards are drawn from it atomically.
            $table->unsignedBigInteger('point_budget')->default(0);
            $table->unsignedBigInteger('points_spent')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sponsor_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('role', 16)->default('owner');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('address')->nullable();
            $table->string('city', 64)->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_m')->default(60);
            $table->json('opening_hours')->nullable();
            $table->text('qr_secret');
            $table->string('status', 16)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['latitude', 'longitude']);
            $table->index(['status', 'sponsor_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('image_path')->nullable();
            $table->string('discount_type', 16);
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->unsignedBigInteger('min_purchase_rial')->nullable();
            // Optional shared code for online use; in-store redemption always uses the per-user code.
            $table->string('shared_code', 32)->nullable();
            // 0 = only obtainable from campaigns; > 0 = claimable with points.
            $table->unsignedInteger('point_cost')->default(0);
            $table->boolean('claimable')->default(false);
            $table->unsignedSmallInteger('valid_days')->default(30);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedSmallInteger('per_user_limit')->default(1);
            $table->unsignedInteger('claimed_count')->default(0);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->string('status', 24)->default('draft');
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'claimable']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('verification_method', 24)->default('geofence_qr');
            $table->unsignedSmallInteger('min_stay_seconds')->default(180);
            $table->unsignedInteger('reward_points')->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('max_rewards_per_user')->default(1);
            $table->unsignedSmallInteger('cooldown_hours')->default(24);
            $table->unsignedInteger('total_limit')->nullable();
            $table->unsignedInteger('rewards_count')->default(0);
            $table->unsignedBigInteger('point_budget')->default(0);
            $table->unsignedBigInteger('points_spent')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 24)->default('draft');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_location', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->primary(['campaign_id', 'location_id']);
            $table->index('location_id');
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->string('status', 16)->default('started');
            // All durations are measured with the server clock.
            $table->timestamp('entered_at');
            $table->timestamp('last_ping_at');
            $table->timestamp('last_inside_at')->nullable();
            $table->unsignedInteger('stay_seconds')->default(0);
            $table->unsignedSmallInteger('pings_count')->default(0);
            $table->unsignedSmallInteger('outside_pings')->default(0);
            $table->unsignedInteger('min_distance_m')->nullable();
            $table->unsignedSmallInteger('best_accuracy_m')->nullable();
            // Only the last accepted position is kept (to detect teleports); cleared when the visit closes.
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->char('qr_token_hash', 64)->nullable();
            $table->timestamp('qr_verified_at')->nullable();
            $table->unsignedTinyInteger('fraud_score')->default(0);
            $table->string('rejection_reason', 64)->nullable();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->foreignId('point_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'campaign_id', 'created_at']);
            $table->index(['user_id', 'location_id', 'status']);
            $table->index(['campaign_id', 'status']);
            $table->index(['status', 'last_ping_at']);
            // A QR token counts once per user (several customers can scan the same screen).
            $table->unique(['user_id', 'qr_token_hash']);
        });

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('coupon_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('code', 16)->unique();
            $table->string('status', 16)->default('available');
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 128);
            $table->timestamp('claimed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('sponsor_users')->nullOnDelete();
            $table->foreignId('redeemed_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['coupon_id', 'user_id']);
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        foreach (['user_coupons', 'visits', 'campaign_location', 'campaigns', 'coupons', 'locations', 'sponsor_users', 'sponsors'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
