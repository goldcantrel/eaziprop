<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Payment;
use App\Models\MaintenanceRequest;
use App\Models\Document;
use App\Models\ChatMessage;
use App\Policies\UserPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\RentalPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\MaintenanceRequestPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ChatMessagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Property::class => PropertyPolicy::class,
        Rental::class => RentalPolicy::class,
        Payment::class => PaymentPolicy::class,
        MaintenanceRequest::class => MaintenanceRequestPolicy::class,
        Document::class => DocumentPolicy::class,
        ChatMessage::class => ChatMessagePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user->role === 'superuser') {
                return true;
            }
        });
    }
}