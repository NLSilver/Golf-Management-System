<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Sample rate card - placeholder prices the facility can edit
 * later from the admin screen or straight in the services table.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['BAY-HR', 'Hitting Bay (per hour)', Service::CATEGORY_BAY_TIME, 'hour', 300.00, 90, 1],
            ['CLUB-RENT', 'Golf Club Rental', Service::CATEGORY_CLUB_RENTAL, 'session', 150.00, null, 2],
            ['LESSON-SES', 'On-site Instructor Session', Service::CATEGORY_LESSON, 'session', 600.00, null, 3],
            ['FOOD-PLATTER', 'Dimyum Meal Platter', Service::CATEGORY_FOOD, 'piece', 250.00, null, 10],
            ['FOOD-MEAL', 'Dimyum Rice Meal', Service::CATEGORY_FOOD, 'piece', 180.00, null, 11],
            ['FOOD-DRINK', 'Softdrink 500ml', Service::CATEGORY_FOOD, 'piece', 45.00, null, 12],
            ['FOOD-WATER', 'Bottled Water 500ml', Service::CATEGORY_FOOD, 'piece', 30.00, null, 13],
        ];

        foreach ($services as [$code, $name, $category, $unit, $rate, $minimum, $sort]) {
            Service::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'rate' => $rate,
                    'minimum_minutes' => $minimum,
                    'is_active' => true,
                    'sort_order' => $sort,
                ],
            );
        }
    }
}
