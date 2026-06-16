<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PropertyPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'properties.read',
            'properties.create',
            'properties.update',
            'properties.delete',
            'property-categories.read',
            'property-categories.create',
            'property-categories.update',
            'property-categories.delete',
            'citizen-properties.read',
            'citizen-properties.create',
            'citizen-properties.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }
    }
}
