<?php

namespace App\Events;

use App\Models\MaintenanceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $maintenanceRequest;
    public $previousStatus;

    public function __construct(MaintenanceRequest $maintenanceRequest, string $previousStatus)
    {
        $this->maintenanceRequest = $maintenanceRequest;
        $this->previousStatus = $previousStatus;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('maintenance.' . $this->maintenanceRequest->id);
    }
}