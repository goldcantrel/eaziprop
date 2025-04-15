<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PropertyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Property $property)
    {
        return $user->role === 'superuser' ||
            $user->id === $property->landlord_id ||
            $property->rentals()->where('tenant_id', $user->id)->exists();
    }

    public function create(User $user)
    {
        return $user->role === 'superuser' || $user->role === 'landlord';
    }

    public function update(User $user, Property $property)
    {
        return $user->role === 'superuser' || $user->id === $property->landlord_id;
    }

    public function delete(User $user, Property $property)
    {
        return $user->role === 'superuser' || $user->id === $property->landlord_id;
    }

    public function chat(User $user, Property $property)
    {
        return $this->view($user, $property);
    }
}