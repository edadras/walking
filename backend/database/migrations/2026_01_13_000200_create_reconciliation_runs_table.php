<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 12)->index(); // ok | issues
            $table->unsignedInteger('wallets_checked');
            $table->unsignedInteger('cashouts_checked');
            $table->unsignedInteger('issue_count');
            $table->json('issues');   // capped list of findings
            $table->json('totals');   // points in circulation, payouts, …
            $table->timestamp('started_at');
            $table->timestamp('finished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
