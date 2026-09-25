<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashout_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_score')->nullable()->index();
            $table->json('risk_signals')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cashout_requests', fn (Blueprint $table) => $table->dropColumn(['risk_score', 'risk_signals']));
    }
};
