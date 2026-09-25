<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sponsor buys points for its budget pool online (instead of an admin top-up after an offline invoice).
        Schema::create('sponsor_top_ups', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sponsor_id')->constrained();
            $table->foreignId('sponsor_user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_rial');
            $table->unsignedBigInteger('points');
            $table->unsignedInteger('price_rial_per_point');
            $table->string('status', 12)->index(); // pending | paid | failed
            $table->string('ref_id', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('sponsor_top_up_id')->nullable()->after('order_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('sponsor_top_up_id'));
        Schema::dropIfExists('sponsor_top_ups');
    }
};
