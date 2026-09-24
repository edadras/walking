<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for date-range scans used by dashboards, CSV reports and scheduled
 * jobs (docs/phase-9.md → «ایندکس‌ها»).
 */
return new class extends Migration
{
    private const INDEXES = [
        'users' => [['created_at']],
        'walking_sessions' => [['local_date', 'status']],
        'point_transactions' => [['created_at']],
        'fraud_events' => [['created_at']],
        'orders' => [['placed_at']],
        'user_coupons' => [['claimed_at'], ['used_at'], ['status', 'expires_at']],
        'visits' => [['created_at']],
        'ad_events' => [['local_date', 'type']],
        'notifications' => [['notifiable_type', 'notifiable_id', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $columns) {
                    $t->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $columns) {
                    $t->dropIndex($columns);
                }
            });
        }
    }
};
