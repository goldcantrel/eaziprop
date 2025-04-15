<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceFollowUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $maintenanceRequest;
    protected $notificationType;

    public function __construct(MaintenanceRequest $maintenanceRequest, string $notificationType)
    {
        $this->maintenanceRequest = $maintenanceRequest;
        $this->notificationType = $notificationType;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $property = $this->maintenanceRequest->property;
        $message = (new MailMessage)
            ->subject('Maintenance Request Follow-up')
            ->greeting("Hello {$notifiable->name},");

        switch ($this->notificationType) {
            case 'pending_landlord':
                $message->line('A maintenance request requires your attention:')
                    ->line("Request Title: {$this->maintenanceRequest->title}")
                    ->line("Property: {$property->name}")
                    ->line("This request has been pending for more than 48 hours.")
                    ->action('Review Request', url('/maintenance/' . $this->maintenanceRequest->id));
                break;

            case 'in_progress_assigned':
                $message->line('A maintenance request you are assigned to needs an update:')
                    ->line("Request Title: {$this->maintenanceRequest->title}")
                    ->line("Property: {$property->name}")
                    ->line("This request has been in progress for more than 7 days.")
                    ->action('Update Status', url('/maintenance/' . $this->maintenanceRequest->id));
                break;

            case 'in_progress_landlord':
                $message->line('A maintenance request needs your attention:')
                    ->line("Request Title: {$this->maintenanceRequest->title}")
                    ->line("Property: {$property->name}")
                    ->line("This request has been in progress for more than 7 days without updates.")
                    ->action('View Details', url('/maintenance/' . $this->maintenanceRequest->id));
                break;

            case 'in_progress_tenant':
                $message->line('Update on your maintenance request:')
                    ->line("Request Title: {$this->maintenanceRequest->title}")
                    ->line("Property: {$property->name}")
                    ->line("We are still working on your request. There has been a delay in completion.")
                    ->line("We appreciate your patience.")
                    ->action('View Status', url('/maintenance/' . $this->maintenanceRequest->id));
                break;
        }

        return $message->line('Thank you for using our property management system.');
    }

    public function toArray($notifiable)
    {
        return [
            'request_id' => $this->maintenanceRequest->id,
            'property_id' => $this->maintenanceRequest->property_id,
            'title' => $this->maintenanceRequest->title,
            'status' => $this->maintenanceRequest->status,
            'notification_type' => $this->notificationType,
            'days_in_status' => $this->maintenanceRequest->updated_at->diffInDays(now()),
        ];
    }
}