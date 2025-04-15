<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->role === 'superuser') {
            $requests = MaintenanceRequest::with(['property', 'tenant', 'assignedTo'])->get();
        } elseif ($user->role === 'landlord') {
            $requests = MaintenanceRequest::whereHas('property', function($query) use ($user) {
                $query->where('landlord_id', $user->id);
            })->with(['property', 'tenant', 'assignedTo'])->get();
        } else {
            $requests = MaintenanceRequest::where('tenant_id', $user->id)
                ->with(['property', 'assignedTo'])->get();
        }

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,emergency'
        ]);

        $maintenanceRequest = MaintenanceRequest::create([
            ...$request->validated(),
            'tenant_id' => Auth::id(),
            'status' => 'pending'
        ]);

        return response()->json($maintenanceRequest->load(['property', 'tenant']), 201);
    }

    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $this->authorize('view', $maintenanceRequest);
        return response()->json($maintenanceRequest->load(['property', 'tenant', 'assignedTo']));
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorize('update', $maintenanceRequest);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'priority' => 'in:low,medium,high,emergency',
            'status' => 'in:pending,approved,in_progress,completed,rejected',
            'assigned_to' => 'exists:users,id',
            'estimated_cost' => 'numeric',
            'actual_cost' => 'numeric'
        ]);

        $maintenanceRequest->update($request->validated());

        if ($request->status === 'completed') {
            $maintenanceRequest->completed_at = now();
            $maintenanceRequest->save();
        }

        return response()->json($maintenanceRequest);
    }

    public function destroy(MaintenanceRequest $maintenanceRequest)
    {
        $this->authorize('delete', $maintenanceRequest);
        $maintenanceRequest->delete();
        return response()->json(null, 204);
    }
}