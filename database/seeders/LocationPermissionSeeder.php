<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LocationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'offices.read',
            'offices.create',
            'offices.update',
            'offices.delete',
            'citizens.read',
            'citizens.create',
            'citizens.update',
            'citizens.delete',
            'citizens.submit',
            'citizen-documents.manage',
            'citizen-photo.manage',
            'citizen-duplicates.check',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'users.disable',
            'users.reset-password',
            'roles.read',
            'roles.create',
            'roles.update',
            'roles.assign',
            'permissions.read',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'audit.read',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'sanctum']);
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);

        $superAdmin->syncPermissions(Permission::where('guard_name', 'sanctum')->pluck('name')->all());
        $admin->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
