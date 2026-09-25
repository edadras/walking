<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Automated inquiry results (Shahkar, civil-registry name match, Sheba owner match).
        foreach (['user_identities', 'bank_accounts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('auto_result', 12)->nullable(); // passed | failed | unavailable
                $table->json('auto_checks')->nullable();
                $table->timestamp('auto_checked_at')->nullable();
            });
        }
        // Bank transfer through a settlement API instead of a manual CSV upload.
        Schema::table('cashout_requests', function (Blueprint $table) {
            $table->string('payout_provider', 20)->nullable();
            $table->uuid('payout_track_id')->nullable()->unique();
            $table->string('payout_state', 32)->nullable();
            $table->string('payout_error', 120)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['user_identities', 'bank_accounts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['auto_result', 'auto_checks', 'auto_checked_at']));
        }
        Schema::table('cashout_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn(['payout_provider', 'payout_track_id', 'payout_state', 'payout_error', 'sent_at']);
        });
    }
};
