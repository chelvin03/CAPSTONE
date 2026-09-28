<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = [
            ['equipment_name' => 'Chairs', 'description' => 'Stackable event chairs', 'total_quantity' => 100, 'unit' => 'piece'],
            ['equipment_name' => 'Tables', 'description' => 'Multipurpose event tables', 'total_quantity' => 20, 'unit' => 'piece'],
            ['equipment_name' => 'Lighting Equipment', 'description' => 'Portable event lighting equipment', 'total_quantity' => 10, 'unit' => 'set'],
            ['equipment_name' => 'Sports Equipment', 'description' => 'Assorted balls, nets, and training equipment', 'total_quantity' => 25, 'unit' => 'set'],
        ];

        foreach ($equipment as $item) {
            Equipment::firstOrCreate(
                ['equipment_name' => $item['equipment_name']],
                [...$item, 'status' => 'available']
            );
        }
    }
}
