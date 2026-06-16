<?php

namespace Database\Seeders;

use App\Models\ProcurementCategory;
use App\Models\ProcurementType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcurementMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Fixed Asset' => [
                'Computer',
                'Laptop',
                'Printer',
                'Scanner',
                'Photocopier',
                'Furniture',
                'Office Equipment',
                'Network Equipment',
                'Air Conditioner',
                'CCTV System',
                'Projector',
                'Building Asset',
            ],
            'Machinery' => [
                'Vehicle',
                'Generator',
                'Tractor',
                'Agricultural Machinery',
                'Construction Machinery',
                'Water Pump',
                'Laboratory Equipment',
                'Heavy Equipment',
                'Production Machine',
            ],
            'Operational' => [
                'Stationery',
                'Fuel',
                'Cleaning Materials',
                'Printing Service',
                'Maintenance Service',
                'Consultancy Service',
                'Training Service',
                'Utility Service',
                'Transport Service',
                'Rental Service',
                'Communication Service',
                'Other Operational Expense',
            ],
        ];

        DB::transaction(function () use ($data): void {
            foreach ($data as $categoryName => $types) {
                $category = ProcurementCategory::query()->updateOrCreate(
                    ['name' => $categoryName],
                    ['name' => $categoryName]
                );

                foreach ($types as $typeName) {
                    ProcurementType::query()->updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'name' => $typeName,
                        ],
                        [
                            'category_id' => $category->id,
                            'name' => $typeName,
                        ]
                    );
                }
            }
        });
    }
}
