<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $sender = $this->message->sender;
        $property = $this->message->property;

        return (new MailMessage)
            ->subject('New Message from ' . $sender->name)
            ->line('You have received a new message regarding property: ' . $property->name)
            ->line('Message: ' . $this->message->message)
            ->action('View Message', url('/chat/' . $property->id))
            ->line('Thank you for using our property management system!');
    }

    public function toArray($notifiable)
    {
        return [
            'message_id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->name,
            'property_id' => $this->message->property_id,
            'property_name' => $this->message->property->name,
            'message' => $this->message->message,
        ];
    }
}