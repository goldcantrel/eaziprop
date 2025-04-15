<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Payment $payment)
    {
        return $user->role === 'superuser' ||
            $payment->rental->property->landlord_id === $user->id ||
            $payment->rental->tenant_id === $user->id;
    }

    public function create(User $user, Payment $payment)
    {
        return $user->role === 'superuser' ||
            $payment->rental->tenant_id === $user->id;
    }

    public function update(User $user, Payment $payment)
    {
        return $user->role === 'superuser' ||
            $payment->rental->property->landlord_id === $user->id;
    }

    public function delete(User $user, Payment $payment)
    {
        return $user->role === 'superuser';
    }
}