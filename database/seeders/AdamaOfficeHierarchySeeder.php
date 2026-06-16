<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdamaOfficeHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $city = Office::updateOrCreate(
            ['type' => Office::TYPE_CITY, 'name' => 'Adama'],
            ['code' => 'CITY-ADAMA', 'parent_id' => null, 'is_active' => true]
        );

        $hierarchy = [
            'Abbaa Gadaa' => ['Badhaatuu', 'Dagaagaa', 'Odaa'],
            'Boolee' => ['Gooroo', 'Dhakaa Adii', 'Dhaddacha Araaraa', 'Andoodee'],
            'Daabee' => ['Caffee', 'Hangaatuu', 'Solloqqee Dongorree'],
            'Bokkuu Shanan' => ['Haroorettii', 'Torban Oboo', 'Hawaash Malkaa Sa’aa'],
            'Luugoo' => ['Barreechaa', 'Migiraa', 'Dirree Nagaa'],
            'Dambalaa' => ['Irreecha', 'Malkaa Adaamaa', 'Wanjii'],
        ];

        foreach ($hierarchy as $subcityName => $woredas) {
            $subcity = Office::updateOrCreate(
                ['type' => Office::TYPE_SUBCITY, 'name' => $subcityName, 'parent_id' => $city->id],
                ['code' => $this->code('SUBCITY', $subcityName), 'is_active' => true]
            );

            foreach ($woredas as $woredaName) {
                $woreda = Office::updateOrCreate(
                    ['type' => Office::TYPE_WOREDA, 'name' => $woredaName, 'parent_id' => $subcity->id],
                    ['code' => $this->code('WOREDA', $subcityName . '-' . $woredaName), 'is_active' => true]
                );

                Office::updateOrCreate(
                    ['type' => Office::TYPE_ZONE, 'name' => 'Zone 01', 'parent_id' => $woreda->id],
                    ['code' => $this->code('ZONE', $subcityName . '-' . $woredaName . '-01'), 'is_active' => true]
                );
            }
        }
    }

    private function code(string $prefix, string $value): string
    {
        return $prefix . '-' . strtoupper(Str::slug($value));
    }
}
