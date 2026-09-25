<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walking_sessions', function (Blueprint $table) {
            $table->unsignedInteger('cycling_distance_m')->default(0)->after('distance_m');
            $table->unsignedInteger('cycling_duration_s')->default(0)->after('cycling_distance_m');
        });
        Schema::table('daily_activities', function (Blueprint $table) {
            $table->unsignedInteger('cycling_distance_m')->default(0)->after('distance_m');
            $table->unsignedInteger('cycling_points')->default(0)->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('walking_sessions', fn (Blueprint $table) => $table->dropColumn(['cycling_distance_m', 'cycling_duration_s']));
        Schema::table('daily_activities', fn (Blueprint $table) => $table->dropColumn(['cycling_distance_m', 'cycling_points']));
    }
};
