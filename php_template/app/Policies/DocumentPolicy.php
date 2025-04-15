<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Document $document)
    {
        return $user->role === 'superuser' ||
            $document->property->landlord_id === $user->id ||
            $document->property->rentals()->where('tenant_id', $user->id)->exists();
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, Document $document)
    {
        return $user->role === 'superuser' ||
            $document->property->landlord_id === $user->id ||
            $document->user_id === $user->id;
    }

    public function delete(User $user, Document $document)
    {
        return $user->role === 'superuser' ||
            $document->property->landlord_id === $user->id ||
            $document->user_id === $user->id;
    }
}