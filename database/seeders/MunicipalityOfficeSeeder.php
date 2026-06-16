<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MunicipalityOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdamaOfficeHierarchySeeder::class);
    }
}
