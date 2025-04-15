<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::query();

        if ($request->user()->role === 'tenant') {
            $query->whereHas('rentals', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->id);
            });
        } elseif ($request->user()->role === 'landlord') {
            $query->where('owner_id', $request->user()->id);
        }

        $properties = $query->with(['owner', 'rentals.tenant'])->paginate(10);

        return response()->json($properties);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'landlord' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'address' => 'required|string|max:255',
            'type' => 'required|string|in:apartment,house,commercial',
            'price' => 'required|numeric|min:0',
            'bedrooms' => 'required|integer|min:0',
            'bathrooms' => 'required|numeric|min:0',
            'square_feet' => 'required|numeric|min:0',
            'available_from' => 'required|date',
            'status' => 'required|string|in:available,rented,maintenance',
            'amenities' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property = Property::create([
            'owner_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'address' => $request->address,
            'type' => $request->type,
            'price' => $request->price,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'square_feet' => $request->square_feet,
            'available_from' => $request->available_from,
            'status' => $request->status,
            'amenities' => $request->amenities
        ]);

        return response()->json([
            'message' => 'Property created successfully',
            'property' => $property->load('owner')
        ], 201);
    }

    public function show(Property $property)
    {
        $user = Auth::user();
        
        if ($user->role === 'tenant' && !$property->rentals()->where('tenant_id', $user->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'landlord' && $property->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'property' => $property->load(['owner', 'rentals.tenant', 'maintenanceRequests'])
        ]);
    }

    public function update(Request $request, Property $property)
    {
        if ($request->user()->role !== 'landlord' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && $property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'address' => 'string|max:255',
            'type' => 'string|in:apartment,house,commercial',
            'price' => 'numeric|min:0',
            'bedrooms' => 'integer|min:0',
            'bathrooms' => 'numeric|min:0',
            'square_feet' => 'numeric|min:0',
            'available_from' => 'date',
            'status' => 'string|in:available,rented,maintenance',
            'amenities' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property->update($request->all());

        return response()->json([
            'message' => 'Property updated successfully',
            'property' => $property->load('owner')
        ]);
    }

    public function destroy(Request $request, Property $property)
    {
        if ($request->user()->role !== 'landlord' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && $property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($property->rentals()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Cannot delete property with active rentals'
            ], 400);
        }

        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully'
        ]);
    }

    public function search(Request $request)
    {
        $query = Property::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('bedrooms')) {
            $query->where('bedrooms', '>=', $request->bedrooms);
        }

        if ($request->has('bathrooms')) {
            $query->where('bathrooms', '>=', $request->bathrooms);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $properties = $query->with('owner')->paginate(10);

        return response()->json($properties);
    }
}