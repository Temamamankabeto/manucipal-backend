<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('properties.read');
    }

    public function view(User $user, Property $property): bool
    {
        return $user->can('properties.read');
    }

    public function create(User $user): bool
    {
        return $user->can('properties.create');
    }

    public function update(User $user, Property $property): bool
    {
        return $user->can('properties.update');
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->can('properties.delete');
    }
}
