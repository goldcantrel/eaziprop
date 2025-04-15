<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RentalController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->role === 'superuser') {
            $rentals = Rental::with(['property', 'tenant'])->get();
        } elseif ($user->role === 'landlord') {
            $rentals = Rental::whereHas('property', function($query) use ($user) {
                $query->where('landlord_id', $user->id);
            })->with(['property', 'tenant'])->get();
        } else {
            $rentals = Rental::where('tenant_id', $user->id)
                ->with(['property'])->get();
        }

        return response()->json($rentals);
    }

    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'tenant_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'monthly_rent' => 'required|numeric',
            'security_deposit' => 'required|numeric',
            'lease_document_url' => 'required|string',
            'payment_due_day' => 'required|integer|min:1|max:31'
        ]);

        $property = Property::findOrFail($request->property_id);
        $this->authorize('create', [Rental::class, $property]);

        $rental = Rental::create($request->validated());
        
        $property->update(['status' => 'rented']);

        return response()->json($rental->load(['property', 'tenant']), 201);
    }

    public function show(Rental $rental)
    {
        $this->authorize('view', $rental);
        return response()->json($rental->load(['property', 'tenant', 'payments']));
    }

    public function update(Request $request, Rental $rental)
    {
        $this->authorize('update', $rental);

        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'monthly_rent' => 'numeric',
            'security_deposit' => 'numeric',
            'status' => 'in:active,pending,ended,terminated',
            'lease_document_url' => 'string',
            'payment_due_day' => 'integer|min:1|max:31'
        ]);

        $rental->update($request->validated());
        return response()->json($rental);
    }

    public function destroy(Rental $rental)
    {
        $this->authorize('delete', $rental);
        
        $property = $rental->property;
        $rental->delete();
        
        if ($property->rentals()->count() === 0) {
            $property->update(['status' => 'available']);
        }

        return response()->json(null, 204);
    }
}