<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['Miguel', 'Delacruz', '09171112222'],
            ['Angela', 'Reyes', '09183334444'],
            ['Paolo', 'Santos', '09195556666'],
            ['Kimberly', 'Tan', '09167778888'],
            ['Ryan', 'Bautista', '09159990000'],
        ];

        foreach ($customers as [$first, $last, $contact]) {
            Customer::query()->firstOrCreate(
                ['first_name' => $first, 'last_name' => $last],
                ['contact_no' => $contact],
            );
        }
    }
}
