<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Release builds are obfuscated per store flavor; symbolising needs version + store.
        Schema::table('client_errors', fn (Blueprint $table) => $table->string('store', 16)->nullable()->after('platform'));
    }

    public function down(): void
    {
        Schema::table('client_errors', fn (Blueprint $table) => $table->dropColumn('store'));
    }
};
