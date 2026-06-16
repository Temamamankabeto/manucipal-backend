<?php

namespace Database\Seeders;

use App\Models\ProcurementCategory;
use App\Models\ProcurementType;
use Illuminate\Database\Seeder;

class ProcurementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Fixed Asset' => [
                'Computer',
                'Laptop',
                'Printer',
                'Scanner',
                'Photocopier',
                'Furniture',
                'Office Equipment',
                'ICT Equipment',
                'Network Equipment',
                'Air Conditioner',
                'CCTV System',
            ],
            'Machinery' => [
                'Vehicle',
                'Generator',
                'Tractor',
                'Agricultural Machinery',
                'Construction Machinery',
                'Heavy Equipment',
                'Water Pump',
                'Laboratory Equipment',
            ],
            'Operational' => [
                'Stationery',
                'Fuel',
                'Cleaning Materials',
                'Printing Service',
                'Maintenance Service',
                'Consultancy Service',
                'Training Service',
                'Transport Service',
                'Utility Service',
            ],
        ];

        foreach ($types as $categoryName => $items) {
            $category = ProcurementCategory::where('name', $categoryName)->first();

            if (! $category) {
                continue;
            }

            foreach ($items as $name) {
                ProcurementType::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ]
                );
            }
        }
    }
}