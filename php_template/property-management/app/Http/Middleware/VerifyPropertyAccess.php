<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Property;

class VerifyPropertyAccess
{
    public function handle(Request $request, Closure $next)
    {
        $propertyId = $request->route('property')?->id ?? $request->property_id;
        
        if (!$propertyId) {
            return $next($request);
        }

        $user = $request->user();
        $property = Property::findOrFail($propertyId);

        // Admin has access to all properties
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Landlord must own the property
        if ($user->role === 'landlord' && $property->owner_id === $user->id) {
            return $next($request);
        }

        // Tenant must have an active rental for the property
        if ($user->role === 'tenant') {
            $hasAccess = $property->rentals()
                ->where('tenant_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($hasAccess) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have access to this property'
        ], 403);
    }
}
