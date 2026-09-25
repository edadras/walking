<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // B2B: companies pay per seat for an employee wellness programme.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('join_code', 10)->unique();
            $table->string('status', 12)->default('active')->index(); // active | suspended
            $table->unsignedInteger('seats');
            $table->unsignedBigInteger('seat_price_rial');             // per seat per month
            $table->date('paid_until')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->json('departments')->nullable();
            $table->timestamps();
        });

        // A user belongs to at most one organization at a time.
        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('department', 60)->nullable();
            $table->timestamp('joined_at');
            $table->timestamps();
        });

        Schema::create('organization_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('role', 16)->default('admin'); // admin | viewer
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('organization_invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('organization_user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('seats');
            $table->unsignedTinyInteger('months');
            $table->unsignedBigInteger('amount_rial');
            $table->string('status', 12)->index(); // pending | paid | failed
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('ref_id', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('organization_invoice_id')->nullable()->after('sponsor_top_up_id')->constrained()->cascadeOnDelete();
        });

        // Company-only challenges (XP and bragging rights; no platform points).
        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('campaign_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_invoice_id'));
        Schema::dropIfExists('organization_invoices');
        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
