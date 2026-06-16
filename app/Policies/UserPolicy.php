<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class UserPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'users.view', 'users.read');
    }

    public function view(User $user, User $model): bool
    {
        return $this->allows($user, 'users.view', 'users.read') && $user->canManageScopeOf($model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->allows($user, 'users.update') && $user->canManageScopeOf($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && $this->allows($user, 'users.delete')
            && $user->canManageScopeOf($model);
    }

    public function toggle(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && $this->allows($user, 'users.toggle', 'users.disable')
            && $user->canManageScopeOf($model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $this->allows($user, 'users.reset-password', 'users.disable')
            && $user->canManageScopeOf($model);
    }

    public function assignRole(User $user, User $model): bool
    {
        return $this->allows($user, 'roles.assign') && $user->canManageScopeOf($model);
    }

    public function rolesLite(User $user): bool
    {
        return $this->allows($user, 'roles.view', 'roles.read', 'users.create', 'users.update');
    }

    public function officesLite(User $user): bool
    {
        return $this->allows($user, 'offices.view', 'users.create', 'users.update', 'users.view', 'users.read');
    }

}
