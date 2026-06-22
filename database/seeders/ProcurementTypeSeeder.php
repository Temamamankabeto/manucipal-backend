<?php

namespace Database\Seeders;

use App\Models\ProcurementCategory;
use App\Models\ProcurementType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcurementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Fixed Asset' => [
                'Furniture',
                'Computer',
                'Laptop',
                'Printer',
                'Photocopier',
                'Office Equipment',
                'Network Equipment',
                'Vehicle',
                'Generator',
                'Building Asset',
            ],
            'Machinery' => [
                'Excavator',
                'Bulldozer',
                'Loader',
                'Grader',
                'Road Roller',
                'Backhoe Loader',
                'Concrete Mixer',
                'Water Pump',
                'Agricultural Machinery',
                'Industrial Machinery',
            ],
        ];

        DB::transaction(function () use ($types): void {
            foreach ($types as $categoryName => $items) {
                $category = ProcurementCategory::query()->where('name', $categoryName)->first();

                if (! $category) {
                    continue;
                }

                foreach ($items as $name) {
                    ProcurementType::query()->updateOrCreate(
                        ['category_id' => $category->id, 'name' => $name],
                        ['category_id' => $category->id, 'name' => $name]
                    );
                }
            }
        });
    }
}
