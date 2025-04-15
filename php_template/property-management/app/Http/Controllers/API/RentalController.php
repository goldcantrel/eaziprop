<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Property;
use App\Models\Payment;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RentalController extends Controller
{
    protected $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Display a listing of rentals.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $query = Rental::query();

            // Filter by tenant or property owner
            if ($request->has('role')) {
                if ($request->role === 'tenant') {
                    $query->where('tenant_email', $user->email);
                } elseif ($request->role === 'landlord') {
                    $query->whereHas('property', function ($q) use ($user) {
                        $q->where('owner_email', $user->email);
                    });
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

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('end_date', '<=', $request->end_date);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Load relationships
            $query->with(['property', 'payments']);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $rentals = $query->paginate($perPage);

            return response()->json($rentals);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch rentals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new rental agreement.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => ['required', 'string', 'exists:properties_593nwd,id'],
            'tenant_email' => ['required', 'email'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['required', 'numeric', 'min:0'],
            'payment_day' => ['required', 'integer', 'between:1,31'],
            'payment_frequency' => ['required', 'string', 'in:monthly,quarterly,yearly']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $property = Property::find($request->property_id);

            // Check property ownership
            if ($property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to create rental for this property'
                ], 403);
            }

            // Check property availability
            if (!$property->isAvailable()) {
                return response()->json([
                    'message' => 'Property is not available for rent'
                ], 400);
            }

            // Create rental agreement
            $rental = new Rental($request->all());
            $rental->status = 'pending_payment';
            $rental->save();

            // Create initial payment records
            $this->createPaymentSchedule($rental);

            // Update property status
            $property->status = 'rented';
            $property->save();

            return response()->json([
                'message' => 'Rental agreement created successfully',
                'rental' => $rental->load(['property', 'payments'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create rental agreement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified rental.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $rental = Rental::with(['property', 'payments'])->find($id);

            if (!$rental) {
                return response()->json([
                    'message' => 'Rental agreement not found'
                ], 404);
            }

            // Add payment statistics
            $rental->payment_statistics = $rental->getPaymentStatistics();

            return response()->json($rental);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch rental agreement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified rental.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_day' => ['sometimes', 'integer', 'between:1,31'],
            'status' => ['sometimes', 'string', 'in:active,terminated,expired']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $rental = Rental::with('property')->find($id);

            if (!$rental) {
                return response()->json([
                    'message' => 'Rental agreement not found'
                ], 404);
            }

            // Check authorization
            if ($rental->property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to update this rental agreement'
                ], 403);
            }

            $rental->update($request->all());

            // Update payment schedule if rent amount or payment day changed
            if ($request->has('rent_amount') || $request->has('payment_day')) {
                $this->updatePaymentSchedule($rental);
            }

            return response()->json([
                'message' => 'Rental agreement updated successfully',
                'rental' => $rental->load('payments')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update rental agreement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminate the rental agreement.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function terminate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'termination_date' => ['required', 'date'],
            'reason' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $rental = Rental::with('property')->find($id);

            if (!$rental) {
                return response()->json([
                    'message' => 'Rental agreement not found'
                ], 404);
            }

            // Check authorization
            if ($rental->property->owner_email !== $user->email && 
                $rental->tenant_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to terminate this rental agreement'
                ], 403);
            }

            $rental->status = 'terminated';
            $rental->end_date = $request->termination_date;
            $rental->save();

            // Update property status
            $rental->property->status = 'available';
            $rental->property->save();

            return response()->json([
                'message' => 'Rental agreement terminated successfully',
                'rental' => $rental
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to terminate rental agreement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment schedule for a rental.
     *
     * @param Rental $rental
     */
    protected function createPaymentSchedule(Rental $rental)
    {
        $startDate = Carbon::parse($rental->start_date);
        $endDate = Carbon::parse($rental->end_date);
        $currentDate = $startDate->copy();

        while ($currentDate->lessThan($endDate)) {
            Payment::create([
                'rental_id' => $rental->id,
                'amount' => $rental->rent_amount,
                'payment_method' => 'pending',
                'payment_date' => null,
                'due_date' => $currentDate->copy()->day($rental->payment_day),
                'status' => 'pending'
            ]);

            // Increment date based on payment frequency
            switch ($rental->payment_frequency) {
                case 'monthly':
                    $currentDate->addMonth();
                    break;
                case 'quarterly':
                    $currentDate->addMonths(3);
                    break;
                case 'yearly':
                    $currentDate->addYear();
                    break;
            }
        }
    }

    /**
     * Update payment schedule for a rental.
     *
     * @param Rental $rental
     */
    protected function updatePaymentSchedule(Rental $rental)
    {
        // Update only future pending payments
        $now = Carbon::now();
        $payments = Payment::where('rental_id', $rental->id)
            ->where('status', 'pending')
            ->where('due_date', '>', $now)
            ->get();

        foreach ($payments as $payment) {
            $payment->amount = $rental->rent_amount;
            $payment->due_date = Carbon::parse($payment->due_date)
                ->day($rental->payment_day);
            $payment->save();
        }
    }
}
