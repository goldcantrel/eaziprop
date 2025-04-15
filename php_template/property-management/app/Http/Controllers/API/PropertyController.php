<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyController extends Controller
{
    protected $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Display a listing of properties.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Property::query();

            // Filter by owner if requested
            if ($request->has('owner')) {
                $query->where('owner_email', $request->owner);
            }

            // Apply search filters
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', "%{$search}%")
                      ->orWhere('address', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Apply type filter
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Apply price range filter
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Apply status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $properties = $query->paginate($perPage);

            return response()->json($properties);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created property.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'address' => ['required', 'string'],
            'type' => ['required', 'string', 'in:apartment,house,condo,townhouse,commercial'],
            'price' => ['required', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'numeric', 'min:0'],
            'square_feet' => ['nullable', 'numeric', 'min:0'],
            'available_from' => ['required', 'date'],
            'amenities' => ['nullable', 'array']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));

            $property = new Property($request->all());
            $property->owner_email = $user->email;
            $property->status = 'available';
            $property->save();

            return response()->json([
                'message' => 'Property created successfully',
                'property' => $property
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified property.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $property = Property::find($id);

            if (!$property) {
                return response()->json([
                    'message' => 'Property not found'
                ], 404);
            }

            // Load associated data
            $property->load(['rentals', 'maintenanceRequests', 'documents']);
            
            // Add statistics
            $property->statistics = $property->getStatistics();

            return response()->json($property);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified property.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', 'in:apartment,house,condo,townhouse,commercial'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'numeric', 'min:0'],
            'square_feet' => ['nullable', 'numeric', 'min:0'],
            'available_from' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:available,rented,maintenance,inactive'],
            'amenities' => ['nullable', 'array']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $property = Property::find($id);

            if (!$property) {
                return response()->json([
                    'message' => 'Property not found'
                ], 404);
            }

            if ($property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to update this property'
                ], 403);
            }

            $property->update($request->all());

            return response()->json([
                'message' => 'Property updated successfully',
                'property' => $property
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified property.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $property = Property::find($id);

            if (!$property) {
                return response()->json([
                    'message' => 'Property not found'
                ], 404);
            }

            if ($property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to delete this property'
                ], 403);
            }

            // Check if property has active rentals
            if ($property->rentals()->where('status', 'active')->exists()) {
                return response()->json([
                    'message' => 'Cannot delete property with active rentals'
                ], 400);
            }

            $property->delete();

            return response()->json([
                'message' => 'Property deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete property',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
