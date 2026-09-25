<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set when the account is deleted but its payout identity must be kept for the books.
        Schema::table('user_identities', function (Blueprint $table) {
            $table->timestamp('retain_until')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_identities', fn (Blueprint $table) => $table->dropColumn('retain_until'));
    }
};
