<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Display a listing of payments.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $query = Payment::query();

            // Join with rentals to check permissions
            $query->whereHas('rental', function ($q) use ($user) {
                $q->where('tenant_email', $user->email)
                  ->orWhereHas('property', function ($q2) use ($user) {
                      $q2->where('owner_email', $user->email);
                  });
            });

            // Filter by rental
            if ($request->has('rental_id')) {
                $query->where('rental_id', $request->rental_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('due_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('due_date', '<=', $request->end_date);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'due_date');
            $sortDir = $request->get('sort_dir', 'asc');
            $query->orderBy($sortBy, $sortDir);

            // Load relationships
            $query->with(['rental.property']);

            // Pagination
            $perPage = $request->get('per_page', 10);
            $payments = $query->paginate($perPage);

            return response()->json($payments);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new payment record.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_id' => ['required', 'string', 'exists:rentals_593nwd,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,credit_card,check'],
            'payment_date' => ['required', 'date'],
            'transaction_id' => ['nullable', 'string'],
            'notes' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $rental = Rental::with('property')->find($request->rental_id);

            // Check authorization
            if ($rental->tenant_email !== $user->email && 
                $rental->property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to create payment for this rental'
                ], 403);
            }

            // Find pending payment with closest due date
            $pendingPayment = Payment::where('rental_id', $rental->id)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->first();

            if (!$pendingPayment) {
                return response()->json([
                    'message' => 'No pending payments found for this rental'
                ], 400);
            }

            // Update pending payment with actual payment details
            $pendingPayment->amount = $request->amount;
            $pendingPayment->payment_method = $request->payment_method;
            $pendingPayment->payment_date = $request->payment_date;
            $pendingPayment->transaction_id = $request->transaction_id;
            $pendingPayment->notes = $request->notes;
            $pendingPayment->status = 'completed';
            $pendingPayment->save();

            // Update rental status if it was pending payment
            if ($rental->status === 'pending_payment') {
                $rental->status = 'active';
                $rental->save();
            }

            return response()->json([
                'message' => 'Payment recorded successfully',
                'payment' => $pendingPayment->load('rental.property')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $payment = Payment::with('rental.property')->find($id);

            if (!$payment) {
                return response()->json([
                    'message' => 'Payment not found'
                ], 404);
            }

            return response()->json($payment);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status (e.g., mark as failed or refunded).
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:failed,refunded'],
            'notes' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $payment = Payment::with('rental.property')->find($id);

            if (!$payment) {
                return response()->json([
                    'message' => 'Payment not found'
                ], 404);
            }

            // Check authorization (only property owner can update payment status)
            if ($payment->rental->property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to update payment status'
                ], 403);
            }

            // Update payment status
            $payment->status = $request->status;
            if ($request->has('notes')) {
                $payment->notes = $request->notes;
            }
            $payment->save();

            return response()->json([
                'message' => 'Payment status updated successfully',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

            $query = Payment::query();

            // Filter by user role
            $query->whereHas('rental', function ($q) use ($user) {
                $q->where('tenant_email', $user->email)
                  ->orWhereHas('property', function ($q2) use ($user) {
                      $q2->where('owner_email', $user->email);
                  });
            });

            // Apply date range filter
            $query->whereBetween('due_date', [$startDate, $endDate]);

            $statistics = [
                'total_paid' => $query->where('status', 'completed')->sum('amount'),
                'total_pending' => $query->where('status', 'pending')->sum('amount'),
                'total_failed' => $query->where('status', 'failed')->sum('amount'),
                'total_refunded' => $query->where('status', 'refunded')->sum('amount'),
                'payment_methods' => $query->where('status', 'completed')
                    ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                    ->groupBy('payment_method')
                    ->get(),
                'monthly_totals' => $query->where('status', 'completed')
                    ->selectRaw('DATE_TRUNC(\'month\', payment_date) as month, SUM(amount) as total')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
            ];

            return response()->json($statistics);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch payment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
