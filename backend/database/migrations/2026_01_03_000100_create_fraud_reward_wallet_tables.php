<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('category', 16);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('weight')->default(100);
            $table->json('params')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fraud_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('rule_key', 64)->index();
            $table->unsignedTinyInteger('score');
            $table->string('severity', 16);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['rule_key', 'created_at']);
        });

        Schema::create('fraud_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedTinyInteger('risk_score');
            $table->string('status', 16)->default('open')->index();
            $table->string('reason');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('point_conversion_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rial_per_point');
            $table->timestamp('effective_from')->index();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained();
            $table->unsignedBigInteger('available_balance')->default(0);
            $table->unsignedBigInteger('pending_balance')->default(0);
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->unsignedBigInteger('lifetime_spent')->default(0);
            $table->unsignedBigInteger('lifetime_expired')->default(0);
            $table->timestamps();
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->string('status', 16);
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 128);
            $table->string('description');
            $table->unsignedBigInteger('rial_rate');
            $table->string('performed_by_type', 32)->nullable();
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'available_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('reward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rule_type', 32)->index();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('days_of_week', 20)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('steps')->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->decimal('multiplier', 4, 2)->nullable();
            $table->unsignedInteger('cap')->nullable();
            $table->json('params')->nullable();
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('kind', 32);
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('base_points')->default(0);
            $table->decimal('multiplier', 5, 2)->default(1);
            $table->unsignedInteger('bonus_points')->default(0);
            $table->unsignedInteger('capped_points')->default(0);
            $table->unsignedInteger('final_points');
            $table->json('breakdown')->nullable();
            $table->string('status', 16);
            $table->foreignId('point_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'user_id', 'kind']);
            $table->index(['user_id', 'created_at']);
        });

        // Defence in depth: the ledger service already prevents negatives under a row lock.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE point_transactions ADD CONSTRAINT chk_balance_after CHECK (balance_after IS NULL OR balance_after >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('reward_rules');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('point_conversion_rates');
        Schema::dropIfExists('fraud_cases');
        Schema::dropIfExists('fraud_events');
        Schema::dropIfExists('fraud_rules');
    }
};
