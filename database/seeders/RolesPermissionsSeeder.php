<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
            $this->translationPermissions(),
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
            $this->translationPermissions(),
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
            'procurement.review',
            'procurement.approve',
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
            'translations.view',
            'translations.manage',
        ];

        $branchHeadPermissions = [
            'dashboard.view',

            'procurement.view',
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

        $budgetTeamLeaderPermissions = [
            'dashboard.view',

            'procurement.view',
            'procurement.review',
            'procurement.approve',
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

        $budgetExpertPermissions = [
            'dashboard.view',

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

            'reports.view',
            'notifications.view',
        ];

        return [
            User::ROLE_SUPER_ADMIN => [],
            User::ROLE_ADMIN => $adminPermissions,
            User::ROLE_MANAGER => $managerPermissions,
            User::ROLE_HEAD_DEVELOPMENT_BRANCH => $branchHeadPermissions,
            User::ROLE_HEAD_SERVICE_BRANCH => $branchHeadPermissions,
            User::ROLE_PLANNING_BUDGET_TEAM_LEADER => $budgetTeamLeaderPermissions,
            User::ROLE_PLANNING_BUDGET_EXPERT => $budgetExpertPermissions,

            User::ROLE_ASSET_TEAM_LEADER => [
                'dashboard.view',
                'procurement.view',
                'procurement.review',
                'procurement.asset-review',
                'procurement.reject',
                'procurement.forward',
                'reports.view',
                'notifications.view',
            ],

            User::ROLE_MACHINERY_TEAM_LEADER => [
                'dashboard.view',
                'procurement.view',
                'procurement.review',
                'procurement.asset-review',
                'procurement.reject',
                'procurement.forward',
                'reports.view',
                'notifications.view',
            ],

            User::ROLE_PROCUREMENT_REQUESTER => [
                'dashboard.view',
                'procurement.view',
                'procurement.create',
                'procurement.update',
                'procurement.submit',
                'notifications.view',
            ],

            User::ROLE_PAYMENT_REQUESTER => [
                'dashboard.view',
                'payment.view',
                'payment.read',
                'payment.create',
                'payment.update',
                'payment.submit',
                'notifications.view',
            ],

            User::ROLE_RECORDS_OFFICE => [
                'dashboard.view',

                'procurement.view',
                'procurement.print',
                'procurement.forward',

                'payment.view',
                'payment.read',
                'payment.create',
                'payment.update',
                'payment.submit',
                'payment.print',
                'payment.forward',

                'records.register',
                'records.stamp',
                'records.print',
                'records.archive',

                'reports.view',
                'notifications.view',
            ],

            User::ROLE_FINANCE => [
                'dashboard.view',

                'procurement.view',

                'payment.view',
                'payment.process',
                'payment.disburse',
                'payment.complete',

                'finance.process',
                'finance.disburse',

                'budget.view',
                'budget.transaction.view',

                'reports.view',
                'notifications.view',
            ],

            User::ROLE_FINANCE_ACCOUNTANT => [
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
            ],
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

    protected function translationPermissions(): array
    {
        return [
            'translations.view',
            'translations.manage',
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
            'System Admin' => User::ROLE_SUPER_ADMIN,
            'General Admin' => User::ROLE_ADMIN,
            'City DMIN' => User::ROLE_ADMIN,
            'City Admin' => User::ROLE_ADMIN,
            'Subcity Admin' => User::ROLE_ADMIN,
            'Woreda Admin' => User::ROLE_ADMIN,
            'Zone Admin' => User::ROLE_ADMIN,
            'Zone admin' => User::ROLE_ADMIN,
            'Planning & Budget Experts' => User::ROLE_PLANNING_BUDGET_EXPERT,
            'Finance' => User::ROLE_FINANCE_ACCOUNTANT,
            'Finance Officer' => User::ROLE_FINANCE_ACCOUNTANT,
            'Accountant' => User::ROLE_FINANCE_ACCOUNTANT,
        ];

        foreach ($legacy as $oldRole => $newRole) {
            $old = Role::where('guard_name', $guard)
                ->where('name', $oldRole)
                ->first();

            $new = Role::where('guard_name', $guard)
                ->where('name', $newRole)
                ->first();

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
}