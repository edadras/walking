<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedSmallInteger('key_version')->default(1)->after('public_key_fingerprint');
            $table->timestamp('key_rotated_at')->nullable()->after('key_version');
            // Set by an admin (suspected key compromise): signed calls are refused until the app rotates.
            $table->boolean('key_rotation_required')->default(false)->after('key_rotated_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['key_version', 'key_rotated_at', 'key_rotation_required']));
    }
};
