<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        $query = Rental::query();

        if ($request->user()->role === 'tenant') {
            $query->where('tenant_id', $request->user()->id);
        } elseif ($request->user()->role === 'landlord') {
            $query->whereHas('property', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            });
        }

        $rentals = $query->with(['property', 'tenant'])->paginate(10);

        return response()->json($rentals);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'landlord' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'tenant_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rent_amount' => 'required|numeric|min:0',
            'deposit_amount' => 'required|numeric|min:0',
            'payment_day' => 'required|integer|between:1,31',
            'status' => 'required|string|in:pending,active,terminated,expired',
            'payment_frequency' => 'required|string|in:monthly,quarterly,yearly'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property = Property::findOrFail($request->property_id);

        if ($request->user()->role === 'landlord' && $property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($property->status !== 'available') {
            return response()->json([
                'message' => 'Property is not available for rent'
            ], 400);
        }

        $rental = Rental::create($request->all());

        $property->update(['status' => 'rented']);

        return response()->json([
            'message' => 'Rental agreement created successfully',
            'rental' => $rental->load(['property', 'tenant'])
        ], 201);
    }

    public function show(Rental $rental)
    {
        $user = $request->user();

        if ($user->role === 'tenant' && $rental->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'landlord' && $rental->property->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'rental' => $rental->load(['property', 'tenant', 'payments'])
        ]);
    }

    public function update(Request $request, Rental $rental)
    {
        if ($request->user()->role !== 'landlord' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && $rental->property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'end_date' => 'date|after:start_date',
            'rent_amount' => 'numeric|min:0',
            'deposit_amount' => 'numeric|min:0',
            'payment_day' => 'integer|between:1,31',
            'status' => 'string|in:pending,active,terminated,expired',
            'payment_frequency' => 'string|in:monthly,quarterly,yearly'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rental->update($request->all());

        if ($request->status === 'terminated' || $request->status === 'expired') {
            $rental->property->update(['status' => 'available']);
        }

        return response()->json([
            'message' => 'Rental agreement updated successfully',
            'rental' => $rental->load(['property', 'tenant'])
        ]);
    }

    public function destroy(Request $request, Rental $rental)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($rental->status === 'active') {
            return response()->json([
                'message' => 'Cannot delete active rental agreement'
            ], 400);
        }

        $rental->delete();

        return response()->json([
            'message' => 'Rental agreement deleted successfully'
        ]);
    }

    public function getRentalHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'exists:properties,id',
            'tenant_id' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Rental::query();

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        $rentals = $query->with(['property', 'tenant', 'payments'])
                         ->orderBy('created_at', 'desc')
                         ->paginate(10);

        return response()->json($rentals);
    }
}