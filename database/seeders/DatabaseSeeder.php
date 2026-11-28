<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Initial accounts. Change these passwords before any real deployment.
        User::updateOrCreate(['email' => 'admin@tixora.test'], [
            'name' => 'Admin JualTiket', 'password' => 'password', 'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
        User::updateOrCreate(['email' => 'petugas@tixora.test'], [
            'name' => 'Petugas Gate 1', 'password' => 'password', 'role' => User::ROLE_OFFICER, 'is_active' => true,
        ]);
        User::updateOrCreate(['email' => 'customer@tixora.test'], [
            'name' => 'Customer Demo', 'password' => 'password', 'role' => User::ROLE_CUSTOMER, 'is_active' => true,
        ]);

        Setting::put('site_name', 'JualTiket');
        Setting::put('admin_fee', '2500');
        Setting::put('payment_window_minutes', '60');

        if (app()->environment('local')) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
