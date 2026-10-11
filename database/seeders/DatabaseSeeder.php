<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            BaySeeder::class,
            ServiceSeeder::class,
            EmployeeSeeder::class,
            CustomerSeeder::class,
            UserSeeder::class,
            // Demo activity - skips itself if the table already has rows.
            AttendanceSeeder::class,
            SessionSeeder::class,
            TransactionSeeder::class,
            InventorySeeder::class,
            MaintenanceLogSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
