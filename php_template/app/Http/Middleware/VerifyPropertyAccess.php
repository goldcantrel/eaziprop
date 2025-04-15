<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Property;

class VerifyPropertyAccess
{
    public function handle(Request $request, Closure $next)
    {
        $propertyId = $request->route('property') 
            ? $request->route('property')->id 
            : $request->input('property_id');

        if (!$propertyId) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $user = Auth::user();
        $property = Property::find($propertyId);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        if ($user->role === 'superuser') {
            return $next($request);
        }

        if ($user->role === 'landlord' && $property->landlord_id === $user->id) {
            return $next($request);
        }

        if ($user->role === 'tenant' && $property->rentals()->where('tenant_id', $user->id)->exists()) {
            return $next($request);
        }

        return response()->json(['message' => 'Access denied'], 403);
    }
}