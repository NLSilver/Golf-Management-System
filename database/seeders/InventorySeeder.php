<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Stock levels around the reorder line so the low-stock path is visible. */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        if (InventoryItem::query()->exists()) {
            return;
        }

        $frontdesk = User::query()->where('username', 'frontdesk')->first();

        $items = [
            // code, name, category, qty, unit, reorder level
            ['RANGE-BALL', 'Range Balls (refurbished)', 'balls', 840, 'pcs', 500],
            ['PRACT-BALL', 'Practice Balls (new)', 'balls', 260, 'pcs', 300], // low stock
            ['CLUB-RM', 'Rental Clubs (men)', 'clubs', 18, 'sets', 6],
            ['CLUB-RW', 'Rental Clubs (women)', 'clubs', 9, 'sets', 6],
            ['CLUB-JR', 'Junior Clubs', 'clubs', 4, 'sets', 5], // low stock
            ['GLOVE-STD', 'Gloves (standard)', 'apparel', 22, 'pcs', 10],
            ['TOWEL', 'Face Towels', 'apparel', 35, 'pcs', 15],
            ['TEE-PACK', 'Tee Packs (50 pcs)', 'accessories', 28, 'packs', 12],
        ];

        foreach ($items as [$code, $name, $category, $qty, $unit, $reorder]) {
            $item = InventoryItem::query()->create([
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'quantity' => $qty,
                'unit' => $unit,
                'reorder_level' => $reorder,
                'is_active' => true,
            ]);

            // Opening stock plus a week of daily consumption.
            InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => 'in',
                'quantity' => $qty,
                'reference' => 'Opening stock',
                'notes' => 'Initial delivery from supplier',
                'user_id' => $frontdesk?->id,
                'created_at' => now()->subDays(20),
            ]);

            foreach (range(1, 5) as $day) {
                InventoryMovement::query()->create([
                    'inventory_item_id' => $item->id,
                    'type' => 'out',
                    'quantity' => max(1, (int) ($qty * 0.02)),
                    'reference' => 'Daily range use',
                    'notes' => now()->subDays($day)->format('D').' bucket allocations',
                    'user_id' => $frontdesk?->id,
                    'created_at' => now()->subDays($day)->setTime(18, 30),
                ]);
            }
        }
    }
}
