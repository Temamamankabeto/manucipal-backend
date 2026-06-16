<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Services\AuditLogService;
use Illuminate\Validation\ValidationException;

class RoleService
{
    protected string $guard = 'sanctum';

    public function paginateRoles(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));

        $query = Role::query()
            ->where('guard_name', $this->guard)
            ->orderBy('name');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate($perPage);
    }

    public function transformPaginatedRoles(LengthAwarePaginator $roles): array
    {
        return [
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'last_page' => $roles->lastPage(),
            ],
        ];
    }

    public function getRole(int|string $id): Role
    {
        return Role::query()
            ->where('id', $id)
            ->where('guard_name', $this->guard)
            ->firstOrFail();
    }

    public function getPermissions(?string $search = null)
    {
        $query = Permission::query()->where('guard_name', $this->guard)->orderBy('name');
        $search = trim((string) $search);

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get(['id', 'name', 'guard_name']);
    }

    public function createRole(array $data): Role
    {
        $role = Role::firstOrCreate(['name' => $data['name'], 'guard_name' => $this->guard]);
        $this->clearPermissionCache();

        AuditLogService::log('role_management', 'CREATE', $role, [
            'after' => ['name' => $role->name],
        ]);

        return $role;
    }

    public function updateRole(Role $role, array $data): Role
    {
        $before = ['name' => $role->name];
        $role->update(['name' => $data['name']]);
        $this->clearPermissionCache();

        AuditLogService::log('role_management', 'UPDATE', $role, [
            'before' => $before,
            'after' => ['name' => $role->name],
        ]);

        return $role->fresh();
    }

    public function getRolePermissions(Role $role)
    {
        return $role->permissions()->where('guard_name', $this->guard)->orderBy('name')->get(['id', 'name', 'guard_name']);
    }

    public function assignPermissions(Role $role, array $permissionNames = []): array
    {
        $permNames = collect($permissionNames)->map(fn ($name) => trim((string) $name))->filter()->values()->all();

        $existingPermissions = Permission::query()
            ->where('guard_name', $this->guard)
            ->whereIn('name', $permNames)
            ->get();

        $before = $role->permissions()->pluck('name')->values()->all();

        $role->syncPermissions($existingPermissions);
        $this->clearPermissionCache();

        AuditLogService::log('role_management', 'ASSIGN_PERMISSIONS', $role, [
            'before' => ['permissions' => $before],
            'after' => ['permissions' => $existingPermissions->pluck('name')->values()->all()],
        ]);

        return [
            'role_id' => $role->id,
            'assigned_count' => $existingPermissions->count(),
            'permissions' => $existingPermissions->pluck('name')->values(),
        ];
    }


    public function deleteRole(Role $role): void
    {
        $protectedRoles = [
            'super-admin',
            'super admin',
            'admin',
            'manager',
        ];

        if (in_array(strtolower($role->name), $protectedRoles, true)) {
            throw ValidationException::withMessages([
                'role' => ['This system role cannot be deleted.'],
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => ['Cannot delete this role while users are assigned to it. Remove users from the role first.'],
            ]);
        }

        $before = [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions()->pluck('name')->values()->all(),
        ];

        $role->syncPermissions([]);
        $role->delete();
        $this->clearPermissionCache();

        AuditLogService::log('role_management', 'DELETE', $role, [
            'before' => $before,
        ]);
    }

    protected function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
