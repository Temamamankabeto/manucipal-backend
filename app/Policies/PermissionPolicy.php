<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->can('permissions.view') || $user->can('permissions.read') || $user->can('roles.assign-permissions');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin() || $user->can('permissions.view') || $user->can('permissions.read') || $user->can('roles.assign-permissions');
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->can('permissions.create');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin() || $user->can('permissions.update');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin() || $user->can('permissions.delete');
    }
}
