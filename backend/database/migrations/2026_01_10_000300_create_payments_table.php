<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per gateway attempt for a rial order. The amount is ours: the
        // gateway callback never decides how much was due.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 24);
            $table->string('authority', 64)->unique();
            $table->unsignedBigInteger('amount_rial');
            $table->string('pay_url', 255);
            $table->string('status', 16)->index(); // pending | paid | failed | cancelled
            $table->string('ref_id', 64)->nullable();
            $table->string('card_pan', 32)->nullable();
            $table->string('failure', 120)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
