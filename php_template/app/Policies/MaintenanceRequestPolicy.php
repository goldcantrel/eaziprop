<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, MaintenanceRequest $request)
    {
        return $user->role === 'superuser' ||
            $request->property->landlord_id === $user->id ||
            $request->tenant_id === $user->id ||
            $request->assigned_to === $user->id;
    }

    public function create(User $user)
    {
        return $user->role === 'tenant';
    }

    public function update(User $user, MaintenanceRequest $request)
    {
        return $user->role === 'superuser' ||
            $request->property->landlord_id === $user->id ||
            ($user->role === 'tenant' && $request->tenant_id === $user->id);
    }

    public function delete(User $user, MaintenanceRequest $request)
    {
        return $user->role === 'superuser' ||
            $request->property->landlord_id === $user->id;
    }

    public function assign(User $user, MaintenanceRequest $request)
    {
        return $user->role === 'superuser' ||
            $request->property->landlord_id === $user->id;
    }
}