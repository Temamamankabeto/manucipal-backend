<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LocationManagementSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdamaOfficeHierarchySeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'offices.read', 'offices.create', 'offices.update', 'offices.delete',
            'locations.read', 'locations.create', 'locations.update', 'locations.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $existing = $role->permissions()->pluck('name')->all();
            $role->syncPermissions(array_values(array_unique([...$existing, ...$permissions])));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
