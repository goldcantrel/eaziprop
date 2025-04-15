<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $maintenanceRequest;
    protected $previousStatus;

    public function __construct(MaintenanceRequest $maintenanceRequest, string $previousStatus)
    {
        $this->maintenanceRequest = $maintenanceRequest;
        $this->previousStatus = $previousStatus;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $property = $this->maintenanceRequest->property;
        $statusChange = "Status changed from {$this->previousStatus} to {$this->maintenanceRequest->status}";

        return (new MailMessage)
            ->subject('Maintenance Request Update - ' . $property->name)
            ->line('The maintenance request has been updated:')
            ->line('Title: ' . $this->maintenanceRequest->title)
            ->line($statusChange)
            ->line('Priority: ' . $this->maintenanceRequest->priority)
            ->action('View Request', url('/maintenance-requests/' . $this->maintenanceRequest->id))
            ->line('Thank you for using our property management system!');
    }

    public function toArray($notifiable)
    {
        return [
            'request_id' => $this->maintenanceRequest->id,
            'property_id' => $this->maintenanceRequest->property_id,
            'property_name' => $this->maintenanceRequest->property->name,
            'title' => $this->maintenanceRequest->title,
            'previous_status' => $this->previousStatus,
            'current_status' => $this->maintenanceRequest->status,
            'priority' => $this->maintenanceRequest->priority,
        ];
    }
}