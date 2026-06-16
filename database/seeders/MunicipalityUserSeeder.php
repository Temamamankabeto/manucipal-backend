<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MunicipalityUserSeeder extends Seeder
{
    public function run(): void
    {
        $city = Office::where('type', Office::TYPE_CITY)->orderBy('id')->first();
        $subcity = Office::where('type', Office::TYPE_SUBCITY)->orderBy('id')->first();
        $woreda = Office::where('type', Office::TYPE_WOREDA)->orderBy('id')->first();
        $zone = Office::where('type', Office::TYPE_ZONE)->orderBy('id')->first();

        $this->createUser('Super Admin', 'super.admin@adama.gov.et', '+251900000001', User::ROLE_SUPER_ADMIN, null, $city);
        $this->createUser('City Admin', 'city.admin@adama.gov.et', '+251900000002', User::ROLE_ADMIN, User::LEVEL_CITY, $city);
        $this->createUser('Subcity Admin', 'subcity.admin@adama.gov.et', '+251900000003', User::ROLE_ADMIN, User::LEVEL_SUBCITY, $subcity);
        $this->createUser('Woreda Admin', 'woreda.admin@adama.gov.et', '+251900000004', User::ROLE_ADMIN, User::LEVEL_WOREDA, $woreda);
        $this->createUser('Zone Admin', 'zone.admin@adama.gov.et', '+251900000005', User::ROLE_ADMIN, User::LEVEL_ZONE, $zone);
        $this->createUser('Asset Manager', 'assetmanager@gmail.com', '+251900000005', User::ASSET_MANAGER, null, $city);


        $legacyAdmin = User::firstOrCreate(
            ['email' => 'admin@aig.com'],
            ['name' => 'Super Admin', 'phone' => '+251900000000', 'password' => Hash::make('Admin@12345'), 'is_active' => true]
        );
        $legacyAdmin->forceFill(['admin_level' => null, 'office_id' => $city?->id])->save();
        $legacyAdmin->syncRoles([User::ROLE_SUPER_ADMIN]);
    }

    protected function createUser(string $name, string $email, string $phone, string $role, ?string $adminLevel, ?Office $office): void
    {
        $scope = $this->scopeFromOffice($office);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make('Admin@12345'),
                'is_active' => true,
                'admin_level' => $role === User::ROLE_SUPER_ADMIN ? null : $adminLevel,
                ...$scope,
            ]
        );

        $user->syncRoles([$role]);
    }

    protected function scopeFromOffice(?Office $office): array
    {
        $scope = ['office_id' => $office?->id, 'sub_city_id' => null, 'woreda_id' => null, 'zone_id' => null];
        $current = $office?->loadMissing('parent.parent.parent');

        while ($current) {
            if ($current->type === Office::TYPE_SUBCITY) {
                $scope['sub_city_id'] = $current->id;
            } elseif ($current->type === Office::TYPE_WOREDA) {
                $scope['woreda_id'] = $current->id;
            } elseif ($current->type === Office::TYPE_ZONE) {
                $scope['zone_id'] = $current->id;
            }

            $current = $current->parent;
        }

        return $scope;
    }
}
