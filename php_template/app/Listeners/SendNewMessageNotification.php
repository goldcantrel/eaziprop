<?php

namespace App\Listeners;

use App\Events\NewChatMessage;
use App\Notifications\NewMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNewMessageNotification implements ShouldQueue
{
    public function handle(NewChatMessage $event)
    {
        $recipient = $event->message->recipient;
        
        if ($recipient->id !== $event->message->sender_id) {
            $recipient->notify(new NewMessageNotification($event->message));
        }
    }
}