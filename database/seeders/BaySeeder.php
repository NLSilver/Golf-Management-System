<?php

namespace Database\Seeders;

use App\Models\Bay;
use Illuminate\Database\Seeder;

class BaySeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 12) as $number) {
            Bay::query()->updateOrCreate(
                ['code' => 'B'.$number],
                ['bay_number' => $number, 'label' => 'Hitting Bay '.$number],
            );
        }
    }
}
