<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->role === 'superuser') {
            $properties = Property::with(['landlord'])->get();
        } elseif ($user->role === 'landlord') {
            $properties = Property::where('landlord_id', $user->id)->get();
        } else {
            $properties = Property::whereHas('rentals', function($query) use ($user) {
                $query->where('tenant_id', $user->id);
            })->get();
        }

        return response()->json($properties);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:apartment,house,condo,commercial',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip_code' => 'required|string',
            'country' => 'required|string',
            'description' => 'required|string',
            'monthly_rent' => 'required|numeric',
            'bedrooms' => 'required|integer',
            'bathrooms' => 'required|integer',
            'square_feet' => 'required|integer',
            'available_from' => 'required|date',
            'minimum_lease_period' => 'required|integer'
        ]);

        $property = Property::create([
            'landlord_id' => Auth::id(),
            ...$request->validated()
        ]);

        return response()->json($property, 201);
    }

    public function show(Property $property)
    {
        $this->authorize('view', $property);
        return response()->json($property->load(['landlord', 'rentals.tenant']));
    }

    public function update(Request $request, Property $property)
    {
        $this->authorize('update', $property);
        
        $request->validate([
            'name' => 'string|max:255',
            'type' => 'in:apartment,house,condo,commercial',
            'address' => 'string',
            'city' => 'string',
            'state' => 'string',
            'zip_code' => 'string',
            'country' => 'string',
            'description' => 'string',
            'monthly_rent' => 'numeric',
            'status' => 'in:available,rented,maintenance,inactive',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'square_feet' => 'integer',
            'available_from' => 'date',
            'minimum_lease_period' => 'integer'
        ]);

        $property->update($request->validated());
        return response()->json($property);
    }

    public function destroy(Property $property)
    {
        $this->authorize('delete', $property);
        $property->delete();
        return response()->json(null, 204);
    }
}