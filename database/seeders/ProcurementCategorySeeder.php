<?php

namespace Database\Seeders;

use App\Models\ProcurementCategory;
use Illuminate\Database\Seeder;

class ProcurementCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Fixed Asset', 'Machinery'] as $name) {
            ProcurementCategory::updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
