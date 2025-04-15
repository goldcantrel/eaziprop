<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\MaintenanceRequestStatusNotification;

class MaintenanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceRequest::query();

        if ($request->user()->role === 'tenant') {
            $query->where('tenant_id', $request->user()->id);
        } elseif ($request->user()->role === 'landlord') {
            $query->whereHas('property', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            });
        } elseif ($request->user()->role === 'staff') {
            $query->where('assigned_to', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $requests = $query->with(['property', 'tenant', 'assignedTo'])
                         ->orderBy('created_at', 'desc')
                         ->paginate(10);

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:low,medium,high,emergency',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property = Property::findOrFail($request->property_id);

        // Verify if user is authorized to create maintenance request for this property
        if ($request->user()->role === 'tenant') {
            $isAuthorized = $property->rentals()
                ->where('tenant_id', $request->user()->id)
                ->where('status', 'active')
                ->exists();

            if (!$isAuthorized) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $maintenanceRequest = MaintenanceRequest::create([
            'property_id' => $request->property_id,
            'tenant_id' => $request->user()->role === 'tenant' ? $request->user()->id : null,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => 'pending',
            'photos' => $request->photos
        ]);

        // Notify property owner
        $property->owner->notify(new MaintenanceRequestStatusNotification($maintenanceRequest));

        return response()->json([
            'message' => 'Maintenance request created successfully',
            'request' => $maintenanceRequest->load(['property', 'tenant'])
        ], 201);
    }

    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $user = $request->user();

        if ($user->role === 'tenant' && $maintenanceRequest->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'landlord' && 
            $maintenanceRequest->property->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'staff' && $maintenanceRequest->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'request' => $maintenanceRequest->load(['property', 'tenant', 'assignedTo'])
        ]);
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if (!in_array($request->user()->role, ['landlord', 'admin', 'staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'string|in:pending,in_progress,completed,cancelled',
            'assigned_to' => 'exists:users,id',
            'notes' => 'nullable|string',
            'estimated_cost' => 'numeric|min:0',
            'actual_cost' => 'numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $maintenanceRequest->update($request->all());

        if ($request->has('status')) {
            // Notify tenant about status change
            $maintenanceRequest->tenant->notify(
                new MaintenanceRequestStatusNotification($maintenanceRequest)
            );
        }

        return response()->json([
            'message' => 'Maintenance request updated successfully',
            'request' => $maintenanceRequest->load(['property', 'tenant', 'assignedTo'])
        ]);
    }

    public function addComment(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = $maintenanceRequest->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->comment
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment->load('user')
        ]);
    }

    public function getStatistics(Request $request)
    {
        $query = MaintenanceRequest::query();

        if ($request->user()->role === 'tenant') {
            $query->where('tenant_id', $request->user()->id);
        } elseif ($request->user()->role === 'landlord') {
            $query->whereHas('property', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            });
        } elseif ($request->user()->role === 'staff') {
            $query->where('assigned_to', $request->user()->id);
        }

        $statistics = [
            'total_requests' => $query->count(),
            'pending_requests' => $query->where('status', 'pending')->count(),
            'in_progress_requests' => $query->where('status', 'in_progress')->count(),
            'completed_requests' => $query->where('status', 'completed')->count(),
            'emergency_requests' => $query->where('priority', 'emergency')->count(),
            'average_completion_time' => $query->whereNotNull('completed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_time')
                ->value('avg_time')
        ];

        return response()->json($statistics);
    }
}