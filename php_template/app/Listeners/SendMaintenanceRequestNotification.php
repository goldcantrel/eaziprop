<?php

namespace App\Listeners;

use App\Events\MaintenanceRequestUpdated;
use App\Notifications\MaintenanceRequestStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMaintenanceRequestNotification implements ShouldQueue
{
    public function handle(MaintenanceRequestUpdated $event)
    {
        $maintenanceRequest = $event->maintenanceRequest;
        $previousStatus = $event->previousStatus;

        // Notify tenant
        $maintenanceRequest->tenant->notify(
            new MaintenanceRequestStatusNotification($maintenanceRequest, $previousStatus)
        );

        // Notify landlord
        $maintenanceRequest->property->landlord->notify(
            new MaintenanceRequestStatusNotification($maintenanceRequest, $previousStatus)
        );

        // Notify assigned maintenance person if exists
        if ($maintenanceRequest->assigned_to) {
            $maintenanceRequest->assignedTo->notify(
                new MaintenanceRequestStatusNotification($maintenanceRequest, $previousStatus)
            );
        }
    }
}