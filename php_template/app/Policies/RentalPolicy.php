<?php

namespace App\Policies;

use App\Models\Rental;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RentalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Rental $rental)
    {
        return $user->role === 'superuser' ||
            $rental->property->landlord_id === $user->id ||
            $rental->tenant_id === $user->id;
    }

    public function create(User $user, Rental $rental)
    {
        return $user->role === 'superuser' ||
            ($user->role === 'landlord' && $rental->property->landlord_id === $user->id);
    }

    public function update(User $user, Rental $rental)
    {
        return $user->role === 'superuser' ||
            $rental->property->landlord_id === $user->id;
    }

    public function delete(User $user, Rental $rental)
    {
        return $user->role === 'superuser' ||
            $rental->property->landlord_id === $user->id;
    }
}