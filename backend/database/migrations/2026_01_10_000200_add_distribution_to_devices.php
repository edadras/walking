<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Where the app was distributed (build flavor) and what installed it. Both are client
            // claims: they only choose which integrity signal to expect, never grant trust.
            $table->string('store', 16)->nullable()->after('manufacturer');
            $table->string('installer', 128)->nullable()->after('store');
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['store', 'installer']));
    }
};
