<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChatMessagePolicy
{
    use HandlesAuthorization;

    public function view(User $user, ChatMessage $message)
    {
        return $user->role === 'superuser' ||
            $message->sender_id === $user->id ||
            $message->recipient_id === $user->id;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, ChatMessage $message)
    {
        return $user->role === 'superuser' ||
            $message->sender_id === $user->id;
    }

    public function delete(User $user, ChatMessage $message)
    {
        return $user->role === 'superuser' ||
            $message->sender_id === $user->id;
    }
}