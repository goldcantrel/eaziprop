<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\NewChatMessage;
use App\Events\MaintenanceRequestUpdated;
use App\Listeners\SendNewMessageNotification;
use App\Listeners\SendMaintenanceRequestNotification;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        NewChatMessage::class => [
            SendNewMessageNotification::class,
        ],
        MaintenanceRequestUpdated::class => [
            SendMaintenanceRequestNotification::class,
        ],
    ];

    public function boot()
    {
        //
    }

    public function shouldDiscoverEvents()
    {
        return false;
    }
}