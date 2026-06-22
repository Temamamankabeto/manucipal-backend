<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'sanctum';

        $permissions = array_values(array_unique(array_merge(
            $this->userManagementPermissions(),
            $this->procurementPermissions(),
            $this->paymentPermissions(),
            $this->budgetPermissions(),
            $this->sharedDocumentWorkflowPermissions(),
        )));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $this->removeCitizenRelatedPermissions($guard);

        foreach ($this->documentRoles() as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);

            if ($roleName === User::ROLE_SUPER_ADMIN) {
                $role->syncPermissions(
                    Permission::where('guard_name', $guard)->pluck('name')->all()
                );

                continue;
            }

            $role->syncPermissions(array_values(array_unique($rolePermissions)));
        }

        $this->convertLegacyRoles();
        $this->grantSuperAdminFullPermissions($guard);
        $this->removeUnsupportedRoles($guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function documentRoles(): array
    {
        $adminPermissions = array_merge(
            ['dashboard.view'],
            $this->userManagementPermissions(),
            $this->procurementPermissions(),
            $this->paymentPermissions(),
            $this->budgetPermissions(),
            $this->sharedDocumentWorkflowPermissions()
        );

        $managerPermissions = [
            'dashboard.view',
            'users.view',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'users.toggle',
            'users.reset-password',
            'roles.view',
            'roles.read',
            'roles.assign-permissions',
            'permissions.view',
            'permissions.read',
            'permissions.assign',
            'offices.view',
            'procurement.view',
            'procurement.create',
            'procurement.update',
            'procurement.delete',
            'procurement.submit',
            'procurement.review',
            'procurement.approve',
            'procurement.asset-review',
            'procurement.reject',
            'procurement.forward',
            'procurement.print',
            'payment.view',
            'payment.read',
            'payment.create',
            'payment.update',
            'payment.submit',
            'payment.review',
            'payment.approve',
            'payment.reject',
            'payment.forward',
            'payment.print',
            'budget.view',
            'budget.create',
            'budget.update',
            'budget.delete',
            'budget.transaction.view',
            'reports.view',
            'audit.view',
            'notifications.view',
        ];

        $branchHeadPermissions = [
            'dashboard.view',
            'procurement.view',
            'procurement.create',
            'procurement.submit',
            'procurement.review',
            'procurement.approve',
            'procurement.reject',
            'procurement.forward',
            'payment.view',
            'payment.read',
            'payment.create',
            'payment.update',
            'payment.submit',
            'payment.review',
            'payment.approve',
            'payment.reject',
            'payment.forward',
            'reports.view',
            'notifications.view',
        ];

        $teamLeaderPermissions = [
            'dashboard.view',
            'users.view',
            'users.read',
            'offices.view',
            'procurement.view',
            'procurement.review',
            'procurement.approve',
            'procurement.asset-review',
            'procurement.reject',
            'procurement.forward',
            'procurement.assign-budget-code',
            'payment.view',
            'payment.read',
            'payment.create',
            'payment.update',
            'payment.submit',
            'payment.review',
            'payment.approve',
            'payment.reject',
            'payment.forward',
            'payment.verify-budget',
            'budget.view',
            'budget.update',
            'budget.transaction.view',
            'reports.view',
            'notifications.view',
        ];

        $expertPermissions = [
            'dashboard.view',
            'offices.view',
            'procurement.view',
            'procurement.update',
            'procurement.form.prepare',
            'procurement.forward',
            'payment.view',
            'payment.read',
            'payment.update',
            'payment.form.prepare',
            'payment.process',
            'payment.forward',
            'budget.view',
            'budget.transaction.view',
            'reports.view',
            'notifications.view',
        ];

        $allUserPaymentRequestPermissions = [
            'payment.view',
            'payment.read',
            'payment.create',
            'payment.update',
            'payment.submit',
        ];

        $allUserProcurementRequestPermissions = [
            'procurement.view',
            'procurement.read',
            'procurement.create',
            'procurement.update',
            'procurement.submit',
        ];

        return [
            User::ROLE_SUPER_ADMIN => [],
            User::ROLE_MANAGER => $managerPermissions,
            User::ROLE_HEAD_DEVELOPMENT_BRANCH => $branchHeadPermissions,
            User::ROLE_HEAD_SERVICE_BRANCH => $branchHeadPermissions,
            User::ROLE_TEAM_LEADER => array_values(array_unique(array_merge($teamLeaderPermissions, $allUserPaymentRequestPermissions, $allUserProcurementRequestPermissions))),
            User::ROLE_EXPERT => array_values(array_unique(array_merge($expertPermissions, $allUserPaymentRequestPermissions, $allUserProcurementRequestPermissions))),
            User::ROLE_SECRETORY => array_values(array_unique(array_merge($allUserPaymentRequestPermissions, $allUserProcurementRequestPermissions, [
                'dashboard.view',
                'users.view',
                'users.read',
                'offices.view',
                'procurement.view',
                'procurement.print',
                'procurement.forward',
                'payment.view',
                'payment.read',
                'payment.print',
                'payment.forward',
                'records.register',
                'records.stamp',
                'records.print',
                'records.archive',
                'notifications.view',
            ]))),
            User::ROLE_ACCOUNTANT => array_values(array_unique(array_merge($allUserPaymentRequestPermissions, $allUserProcurementRequestPermissions, [
                'dashboard.view',
                'payment.view',
                'payment.read',
                'payment.process',
                'payment.complete',
                'finance.process',
                'finance.disburse',
                'budget.view',
                'budget.transaction.view',
                'notifications.view',
            ]))),

            User::ROLE_RECORD_OFFICER => array_values(array_unique(array_merge($allUserPaymentRequestPermissions, $allUserProcurementRequestPermissions, [
                'dashboard.view',
                'users.view',
                'users.read',
                'offices.view',
                'procurement.view',
                'procurement.print',
                'procurement.forward',
                'payment.view',
                'payment.read',
                'payment.print',
                'payment.forward',
                'records.register',
                'records.stamp',
                'records.print',
                'records.archive',
                'notifications.view',
            ]))),
        ];
    }

    protected function userManagementPermissions(): array
    {
        return [
            'dashboard.view',

            'users.view',
            'users.read',
            'users.create',
            'users.update',
            'users.delete',
            'users.toggle',
            'users.reset-password',

            'roles.view',
            'roles.read',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.assign',
            'roles.assign-permissions',

            'permissions.view',
            'permissions.read',
            'permissions.assign',
            'permissions.create',
            'permissions.update',
            'permissions.delete',

            'offices.view',
            'offices.read',
            'offices.create',
            'offices.update',
            'offices.delete',

            'audit.view',
            'audit.read',

            'reports.view',
            'reports.export',

            'notifications.view',
            'notifications.send',
        ];
    }

    protected function procurementPermissions(): array
    {
        return [
            'procurement.view',
            'procurement.read',
            'procurement.create',
            'procurement.update',
            'procurement.delete',
            'procurement.submit',
            'procurement.review',
            'procurement.approve',
            'procurement.asset-review',
            'procurement.reject',
            'procurement.forward',
            'procurement.assign-budget-code',
            'procurement.form.prepare',
            'procurement.print',
            'procurement.complete',

            'procurement-category.view',
            'procurement-category.read',
            'procurement-category.create',
            'procurement-category.update',
            'procurement-category.delete',
            'procurement-type.view',
            'procurement-type.read',
            'procurement-type.create',
            'procurement-type.update',
            'procurement-type.delete',
        ];
    }

    protected function paymentPermissions(): array
    {
        return [
            'payment.view',
            'payment.read',
            'payment.create',
            'payment.update',
            'payment.delete',
            'payment.submit',
            'payment.review',
            'payment.approve',
            'payment.reject',
            'payment.forward',
            'payment.verify-budget',
            'payment.form.prepare',
            'payment.process',
            'payment.print',
            'payment.disburse',
            'payment.complete',

            'payment-category.view',
            'payment-category.read',
            'payment-category.create',
            'payment-category.update',
            'payment-category.delete',
            'payment-type.view',
            'payment-type.read',
            'payment-type.create',
            'payment-type.update',
            'payment-type.delete',
        ];
    }


    protected function budgetPermissions(): array
    {
        return [
            'budget.view',
            'budget.create',
            'budget.update',
            'budget.delete',
            'budget.transaction.view',
        ];
    }

    protected function sharedDocumentWorkflowPermissions(): array
    {
        return [
            'records.register',
            'records.stamp',
            'records.print',
            'records.archive',

            'finance.process',
            'finance.disburse',
        ];
    }

    protected function removeCitizenRelatedPermissions(string $guard): void
    {
        Permission::query()
            ->where('guard_name', $guard)
            ->where(function ($query) {
                $query->where('name', 'like', 'citizen%')
                    ->orWhere('name', 'like', 'citizens.%')
                    ->orWhere('name', 'like', 'dashboard.citizens.%')
                    ->orWhere('name', 'like', 'reports.citizens.%')
                    ->orWhere('name', 'like', 'households.%');
            })
            ->delete();
    }

    protected function convertLegacyRoles(): void
    {
        $guard = 'sanctum';

        $legacy = [
            'Super Admin' => User::ROLE_SUPER_ADMIN,
            'System Admin' => User::ROLE_SUPER_ADMIN,
            'Admin' => User::ROLE_SUPER_ADMIN,
            'General Admin' => User::ROLE_SUPER_ADMIN,
            'City DMIN' => User::ROLE_SUPER_ADMIN,
            'City Admin' => User::ROLE_SUPER_ADMIN,
            'Subcity Admin' => User::ROLE_SUPER_ADMIN,
            'Woreda Admin' => User::ROLE_SUPER_ADMIN,
            'Zone Admin' => User::ROLE_SUPER_ADMIN,
            'Zone admin' => User::ROLE_SUPER_ADMIN,
            'Planning & Budget Team Leader' => User::ROLE_TEAM_LEADER,
            'Asset Team Leader' => User::ROLE_TEAM_LEADER,
            'Machinery Team Leader' => User::ROLE_TEAM_LEADER,
            'Planning & Budget Experts' => User::ROLE_EXPERT,
            'Planning & Budget Expert' => User::ROLE_EXPERT,
            'Payment Requester' => User::ROLE_EXPERT,
            'Procurement Requester' => User::ROLE_EXPERT,
            'Records Office' => User::ROLE_RECORD_OFFICER,
            'Record Office' => User::ROLE_RECORD_OFFICER,
            'Finance' => User::ROLE_ACCOUNTANT,
            'Finance Officer' => User::ROLE_ACCOUNTANT,
            'Finance Accountant' => User::ROLE_ACCOUNTANT,
        ];

        foreach ($legacy as $oldRole => $newRole) {
            if ($oldRole === $newRole) {
                continue;
            }

            $old = Role::where('guard_name', $guard)->where('name', $oldRole)->first();
            $new = Role::where('guard_name', $guard)->where('name', $newRole)->first();

            if (! $old || ! $new) {
                continue;
            }

            User::role($oldRole, $guard)->chunkById(100, function ($users) use ($old, $new) {
                foreach ($users as $user) {
                    $user->removeRole($old);
                    $user->assignRole($new);
                }
            });
        }
    }

    protected function grantSuperAdminFullPermissions(string $guard): void
    {
        $permissions = Permission::where('guard_name', $guard)->pluck('name')->all();

        $superAdminRole = Role::firstOrCreate([
            'name' => User::ROLE_SUPER_ADMIN,
            'guard_name' => $guard,
        ]);

        $superAdminRole->syncPermissions($permissions);

        $superAdminNames = [
            User::ROLE_SUPER_ADMIN,
            'Super Admin',
            'super admin',
            'super-admin',
            'super_admin',
            'SUPER ADMIN',
        ];

        Role::query()
            ->whereIn('name', $superAdminNames)
            ->get()
            ->each(function (Role $role) use ($superAdminRole, $permissions) {
                $role->syncPermissions($permissions);

                $userIds = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', User::class)
                    ->pluck('model_id');

                User::whereIn('id', $userIds)->chunkById(100, function ($users) use ($superAdminRole, $permissions) {
                    foreach ($users as $user) {
                        if (! $user->hasRole($superAdminRole)) {
                            $user->assignRole($superAdminRole);
                        }

                        $user->syncPermissions($permissions);
                    }
                });
            });
    }

    protected function removeUnsupportedRoles(string $guard): void
    {
        Role::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', User::systemRoleNames())
            ->delete();
    }
}
