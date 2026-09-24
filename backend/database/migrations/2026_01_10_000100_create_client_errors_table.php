<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // App crashes grouped by fingerprint: one row per distinct error, counted.
        Schema::create('client_errors', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('error_type', 120);
            $table->string('message', 500);
            $table->text('stack')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('platform', 20)->nullable();
            $table->boolean('fatal')->default(false);
            $table->unsignedInteger('occurrences')->default(1);
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_errors');
    }
};
