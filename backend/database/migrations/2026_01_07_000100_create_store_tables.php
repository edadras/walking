<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('icon', 32)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->string('summary', 250)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 16);
            $table->unsignedInteger('point_price');
            // Only used when the money_payment flag and a gateway are enabled.
            $table->unsignedBigInteger('rial_price')->nullable();
            // NULL = unlimited (services); for digital codes it mirrors the unassigned pool.
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedSmallInteger('max_per_user')->nullable();
            $table->unsignedSmallInteger('min_level')->default(1);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'category_id', 'sort']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 50)->nullable();
            $table->string('recipient', 100);
            $table->string('phone', 20);
            $table->string('province', 50);
            $table->string('city', 50);
            $table->string('line', 300);
            $table->string('postal_code', 10);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 24);
            $table->string('payment_mode', 16)->default('points');
            $table->unsignedBigInteger('total_points');
            $table->unsignedBigInteger('total_rial')->default(0);
            $table->string('idempotency_key', 64);
            // Snapshot: later edits to the address book don't change a placed order.
            $table->json('shipping_address')->nullable();
            $table->string('tracking_code', 64)->nullable();
            $table->foreignId('point_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();
            $table->string('user_note', 300)->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['status', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
        });

        Schema::create('product_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('code');
            $table->char('code_hash', 64)->unique();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'order_item_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('name', 150);
            $table->string('type', 16);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_point_price');
            $table->unsignedBigInteger('unit_rial_price')->default(0);
            $table->foreignId('user_coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('note', 300)->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        foreach (['order_status_histories', 'order_items', 'product_codes', 'orders', 'addresses', 'product_images', 'products', 'categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
