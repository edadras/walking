<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data so the app and panels can be reviewed without empty screens.
 * Never runs in production (see DatabaseSeeder). Extended in later phases.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->firstOrCreate(['email' => 'admin@gamyar.test'], [
            'name' => 'مدیر نمایشی',
            'password' => 'password',
            'role' => AdminRole::SuperAdmin,
        ]);

        $names = ['علی', 'سارا', 'محمد', 'مریم', 'رضا', 'زهرا', 'حسین', 'نگار', 'امیر', 'فاطمه', 'مهدی', 'الهام'];
        foreach ($names as $i => $name) {
            User::factory()->create([
                'phone' => sprintf('+98912000%04d', $i + 1),
                'display_name' => $name,
                'created_at' => now()->subDays(60 - $i * 3),
                'last_active_at' => now()->subHours($i * 5),
            ]);
        }
    }
}
