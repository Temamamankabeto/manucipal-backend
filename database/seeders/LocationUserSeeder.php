<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocationUserSeeder extends Seeder
{
    public function run(): void
    {
        $city = Office::where('code', 'ADAMA')->first();
        $subcity = Office::where('code', 'ADAMA-SC-01')->first();
        $woreda = Office::where('code', 'ADAMA-SC-01-W-01')->first();
        $zone = Office::where('code', 'ADAMA-SC-01-W-01-Z-01')->first();

        $users = [
            ['name' => 'Super Admin', 'email' => 'super.admin@adama.gov.et', 'phone' => '+251900000001', 'role' => 'Super Admin', 'admin_level' => null, 'office_id' => null],
            ['name' => 'City Admin', 'email' => 'city.admin@adama.gov.et', 'phone' => '+251900000002', 'role' => 'Admin', 'admin_level' => Office::TYPE_CITY, 'office_id' => $city?->id],
            ['name' => 'Subcity Admin - Abbaa Gadaa', 'email' => 'subcity.admin@adama.gov.et', 'phone' => '+251900000003', 'role' => 'Admin', 'admin_level' => Office::TYPE_SUBCITY, 'office_id' => $subcity?->id],
            ['name' => 'Woreda Admin - Badhaatuu', 'email' => 'woreda.admin@adama.gov.et', 'phone' => '+251900000004', 'role' => 'Admin', 'admin_level' => Office::TYPE_WOREDA, 'office_id' => $woreda?->id],
            ['name' => 'Zone Admin - Badhaatuu Zone 01', 'email' => 'zone.admin@adama.gov.et', 'phone' => '+251900000005', 'role' => 'Admin', 'admin_level' => Office::TYPE_ZONE, 'office_id' => $zone?->id],
            ['name' => 'General Admin', 'email' => 'admin@aig.com', 'phone' => '+251900000006', 'role' => 'Super Admin', 'admin_level' => null, 'office_id' => null],
        ];

        foreach ($users as $item) {
            $user = User::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'phone' => $item['phone'],
                    'password' => Hash::make('Admin@12345'),
                    'is_active' => true,
                    'admin_level' => $item['admin_level'],
                    'office_id' => $item['office_id'],
                ]
            );

            $user->syncRoles([$item['role']]);
        }
    }
}
