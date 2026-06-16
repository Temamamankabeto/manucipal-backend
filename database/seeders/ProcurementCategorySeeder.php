<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProcurementCategory;

class ProcurementCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Fixed Asset'],
            ['name' => 'Machinery'],
            ['name' => 'Operational'],
        ];

        foreach ($categories as $category) {
            ProcurementCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
    
}