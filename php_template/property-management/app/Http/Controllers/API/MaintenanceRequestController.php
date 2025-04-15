<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\ChatMessage;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaintenanceRequestController extends Controller
{
    protected $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Display a listing of maintenance requests.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $query = MaintenanceRequest::query();

            // Filter by user role
            if ($request->has('role')) {
                switch ($request->role) {
                    case 'tenant':
                        $query->where('tenant_email', $user->email);
                        break;
                    case 'maintenance':
                        $query->where('assigned_to_email', $user->email);
                        break;
                    case 'landlord':
                        $query->whereHas('property', function ($q) use ($user) {
                            $q->where('owner_email', $user->email);
                        });
                        break;
                }
            }

            // Filter by property
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Load relationships
            $query->with(['property', 'chatMessages']);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $requests = $query->paginate($perPage);

            return response()->json($requests);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch maintenance requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new maintenance request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => ['required', 'string', 'exists:properties_593nwd,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high,emergency'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string'] // Base64 encoded images
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $property = Property::find($request->property_id);

            // Check if user is tenant of the property
            $isValidTenant = $property->rentals()
                ->where('tenant_email', $user->email)
                ->where('status', 'active')
                ->exists();

            if (!$isValidTenant) {
                return response()->json([
                    'message' => 'Unauthorized to create maintenance request for this property'
                ], 403);
            }

            // Upload photos to Supabase Storage if provided
            $photoUrls = [];
            if ($request->has('photos')) {
                foreach ($request->photos as $photo) {
                    $filename = uniqid() . '.jpg';
                    $path = "maintenance-photos/{$property->id}/{$filename}";
                    $this->supabase->uploadFile('photos', $path, $photo);
                    $photoUrls[] = $path;
                }
            }

            // Create maintenance request
            $maintenanceRequest = new MaintenanceRequest([
                'property_id' => $request->property_id,
                'tenant_email' => $user->email,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'status' => 'pending',
                'photos' => $photoUrls
            ]);
            $maintenanceRequest->save();

            // Create initial chat message
            ChatMessage::create([
                'sender_email' => $user->email,
                'receiver_email' => $property->owner_email,
                'maintenance_request_id' => $maintenanceRequest->id,
                'message' => "New maintenance request: {$request->title}",
                'message_type' => 'system'
            ]);

            return response()->json([
                'message' => 'Maintenance request created successfully',
                'maintenance_request' => $maintenanceRequest->load(['property', 'chatMessages'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create maintenance request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified maintenance request.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $maintenanceRequest = MaintenanceRequest::with(['property', 'chatMessages'])
                ->find($id);

            if (!$maintenanceRequest) {
                return response()->json([
                    'message' => 'Maintenance request not found'
                ], 404);
            }

            return response()->json($maintenanceRequest);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch maintenance request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified maintenance request.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,cancelled'],
            'assigned_to_email' => ['sometimes', 'email'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $maintenanceRequest = MaintenanceRequest::with('property')->find($id);

            if (!$maintenanceRequest) {
                return response()->json([
                    'message' => 'Maintenance request not found'
                ], 404);
            }

            // Check authorization
            if ($maintenanceRequest->property->owner_email !== $user->email &&
                $maintenanceRequest->assigned_to_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to update this maintenance request'
                ], 403);
            }

            // Handle status change
            if ($request->has('status') && $request->status !== $maintenanceRequest->status) {
                $this->handleStatusChange($maintenanceRequest, $request->status, $user->email);
            }

            $maintenanceRequest->update($request->all());

            return response()->json([
                'message' => 'Maintenance request updated successfully',
                'maintenance_request' => $maintenanceRequest->load(['property', 'chatMessages'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update maintenance request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle maintenance request status change.
     *
     * @param MaintenanceRequest $request
     * @param string $newStatus
     * @param string $userEmail
     */
    protected function handleStatusChange(MaintenanceRequest $request, $newStatus, $userEmail)
    {
        $message = '';
        switch ($newStatus) {
            case 'in_progress':
                $message = "Maintenance request has been started.";
                break;
            case 'completed':
                $message = "Maintenance request has been completed.";
                $request->completed_at = Carbon::now();
                break;
            case 'cancelled':
                $message = "Maintenance request has been cancelled.";
                break;
        }

        if ($message) {
            ChatMessage::create([
                'sender_email' => $userEmail,
                'receiver_email' => $request->tenant_email,
                'maintenance_request_id' => $request->id,
                'message' => $message,
                'message_type' => 'system'
            ]);
        }
    }

    /**
     * Get maintenance request statistics.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $propertyId = $request->get('property_id');

            $statistics = MaintenanceRequest::getStatistics($propertyId);

            return response()->json($statistics);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch maintenance statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
