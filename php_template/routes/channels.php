<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('property.{propertyId}', function ($user, $propertyId) {
    $property = \App\Models\Property::find($propertyId);
    
    if (!$property) {
        return false;
    }

    if ($user->role === 'superuser') {
        return true;
    }

    if ($user->role === 'landlord') {
        return $property->landlord_id === $user->id;
    }

    if ($user->role === 'tenant') {
        return $property->rentals()->where('tenant_id', $user->id)->exists();
    }

    return false;
});

Broadcast::channel('maintenance.{requestId}', function ($user, $requestId) {
    $request = \App\Models\MaintenanceRequest::find($requestId);
    
    if (!$request) {
        return false;
    }

    if ($user->role === 'superuser') {
        return true;
    }

    if ($user->role === 'landlord') {
        return $request->property->landlord_id === $user->id;
    }

    if ($user->role === 'tenant') {
        return $request->tenant_id === $user->id;
    }

    return false;
});