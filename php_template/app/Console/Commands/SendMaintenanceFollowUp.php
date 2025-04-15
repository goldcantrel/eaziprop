<?php

namespace App\Console\Commands;

use App\Models\MaintenanceRequest;
use App\Notifications\MaintenanceFollowUpNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMaintenanceFollowUp extends Command
{
    protected $signature = 'maintenance:follow-up';
    protected $description = 'Send follow-up notifications for pending maintenance requests';

    public function handle()
    {
        $twoDaysAgo = Carbon::now()->subDays(2);
        $sevenDaysAgo = Carbon::now()->subDays(7);

        // Get pending requests that haven't been updated in 2 days
        $pendingRequests = MaintenanceRequest::where('status', 'pending')
            ->where('created_at', '<=', $twoDaysAgo)
            ->with(['property.landlord', 'tenant'])
            ->get();

        foreach ($pendingRequests as $request) {
            // Notify landlord about pending request
            $request->property->landlord->notify(
                new MaintenanceFollowUpNotification($request, 'pending_landlord')
            );
        }

        // Get in-progress requests that haven't been updated in 7 days
        $inProgressRequests = MaintenanceRequest::where('status', 'in_progress')
            ->where('updated_at', '<=', $sevenDaysAgo)
            ->with(['property.landlord', 'tenant', 'assignedTo'])
            ->get();

        foreach ($inProgressRequests as $request) {
            // Notify assigned maintenance person
            if ($request->assigned_to) {
                $request->assignedTo->notify(
                    new MaintenanceFollowUpNotification($request, 'in_progress_assigned')
                );
            }

            // Notify landlord
            $request->property->landlord->notify(
                new MaintenanceFollowUpNotification($request, 'in_progress_landlord')
            );

            // Notify tenant about the delay
            $request->tenant->notify(
                new MaintenanceFollowUpNotification($request, 'in_progress_tenant')
            );
        }

        $this->info('Maintenance request follow-ups sent successfully.');
    }
}