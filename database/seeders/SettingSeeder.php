<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['facility_name', 'Davao EagleMasters Golf Driving Range', 'general'],
            ['facility_address', 'Riverfront Drive, Talomo District, Davao City', 'general'],
            ['currency', 'PHP', 'general'],
            ['currency_symbol', '₱', 'general'],
            ['operating_open', '06:00', 'hours'],
            ['operating_close', '22:00', 'hours'],
            ['minimum_session_minutes', '90', 'bay'],
            ['expiring_soon_minutes', '10', 'bay'],
            ['overstay_grace_minutes', '5', 'bay'],
            ['bay_count', '12', 'bay'],
            ['extension_options', '15,20,90', 'bay'],
            ['receipt_footer', 'Thank you! Play again soon.', 'receipt'],
        ];

        foreach ($settings as [$name, $value, $group]) {
            Setting::setValue($name, $value, $group);
        }
    }
}
