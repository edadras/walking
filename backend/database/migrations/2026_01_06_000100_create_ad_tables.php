<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External networks are adapters; nothing is served from them until enabled AND verified.
        Schema::create('ad_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_enabled')->default(false);
            $table->text('webhook_secret')->nullable();
            $table->text('config')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 100);
            $table->string('format', 16);
            $table->boolean('is_active')->default(true);
            $table->foreignId('ad_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('status', 24)->default('draft');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('priority')->default(10);
            $table->json('targeting')->nullable();
            $table->unsignedInteger('impression_limit')->nullable();
            $table->unsignedInteger('click_limit')->nullable();
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedSmallInteger('frequency_cap_per_day')->nullable();
            // Rewarded campaigns: points per completed view, funded from point_budget.
            $table->unsignedSmallInteger('reward_points')->default(0);
            $table->unsignedBigInteger('point_budget')->default(0);
            $table->unsignedBigInteger('points_spent')->default(0);
            $table->unsignedInteger('rewards_count')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('ad_campaign_placement', function (Blueprint $table) {
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_placement_id')->constrained()->cascadeOnDelete();
            $table->primary(['ad_campaign_id', 'ad_placement_id']);
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('format', 16);
            $table->string('title', 90);
            $table->string('body', 250)->nullable();
            $table->string('image_path')->nullable();
            $table->string('cta_label', 32)->nullable();
            $table->string('action_url', 500)->nullable();
            $table->unsignedSmallInteger('min_view_seconds')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['ad_campaign_id', 'format', 'is_active']);
        });

        // One row per (served ad, event type): the token makes events unforgeable and idempotent.
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_placement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->char('serve_id', 26);
            $table->date('local_date');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['serve_id', 'type']);
            $table->index(['user_id', 'ad_campaign_id', 'local_date', 'type']);
            $table->index(['ad_campaign_id', 'type', 'created_at']);
        });

        Schema::create('ad_views', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ad_id')->constrained();
            $table->foreignId('ad_campaign_id')->constrained();
            $table->foreignId('ad_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('started');
            $table->date('local_date');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('rejection_reason', 64)->nullable();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->foreignId('point_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'local_date', 'status']);
            $table->unique(['ad_provider_id', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        foreach (['ad_views', 'ad_events', 'ads', 'ad_campaign_placement', 'ad_campaigns', 'ad_placements', 'ad_providers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
