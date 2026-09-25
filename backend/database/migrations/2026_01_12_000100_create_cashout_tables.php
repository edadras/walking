<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identity for payouts. National code is encrypted; its keyed hash is unique so one
        // person can't cash out through several accounts.
        Schema::create('user_identities', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('first_name', 50);
            $table->string('last_name', 60);
            $table->text('national_code');
            $table->char('national_code_hash', 64)->unique();
            $table->date('birth_date');
            $table->string('status', 12)->index(); // pending | verified | rejected
            $table->string('rejection_reason', 200)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        // Payout destinations (Sheba). The IBAN is encrypted; its hash is unique across users.
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('iban');
            $table->char('iban_hash', 64)->unique();
            $table->char('iban_last4', 4);
            $table->string('bank_name', 40);
            $table->string('holder_name', 120);
            $table->string('status', 12)->index(); // pending | verified | rejected
            $table->string('rejection_reason', 200)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cashout_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('bank_account_id')->constrained();
            $table->unsignedBigInteger('points');
            $table->unsignedInteger('rial_per_point');
            $table->unsignedBigInteger('amount_rial');
            $table->string('status', 12)->index(); // pending | approved | paid | rejected | cancelled
            $table->string('idempotency_key', 64);
            $table->foreignId('debit_transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('bank_reference', 64)->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('rejection_reason', 200)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashout_requests');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('user_identities');
    }
};
