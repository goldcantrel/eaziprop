<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query();

        if ($request->user()->role === 'tenant') {
            $query->whereHas('rental', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->id);
            });
        } elseif ($request->user()->role === 'landlord') {
            $query->whereHas('rental.property', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            });
        }

        $payments = $query->with(['rental.property', 'rental.tenant'])
                         ->orderBy('due_date', 'desc')
                         ->paginate(10);

        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:credit_card,bank_transfer,cash',
            'payment_date' => 'required|date',
            'status' => 'required|string|in:pending,completed,failed',
            'transaction_id' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rental = Rental::findOrFail($request->rental_id);

        // Verify authorization
        if ($request->user()->role === 'tenant' && $rental->tenant_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && $rental->property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment = Payment::create([
            'rental_id' => $request->rental_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_date' => $request->payment_date,
            'due_date' => Carbon::parse($request->payment_date)->addMonth(),
            'status' => $request->status,
            'transaction_id' => $request->transaction_id,
            'notes' => $request->notes
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully',
            'payment' => $payment->load(['rental.property', 'rental.tenant'])
        ], 201);
    }

    public function show(Payment $payment)
    {
        $user = $request->user();

        if ($user->role === 'tenant' && $payment->rental->tenant_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'landlord' && $payment->rental->property->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'payment' => $payment->load(['rental.property', 'rental.tenant'])
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        if ($request->user()->role !== 'landlord' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && 
            $payment->rental->property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'string|in:pending,completed,failed',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment->update($request->only(['status', 'notes']));

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => $payment->load(['rental.property', 'rental.tenant'])
        ]);
    }

    public function getPaymentHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_id' => 'exists:rentals,id',
            'status' => 'string|in:pending,completed,failed',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Payment::query();

        if ($request->has('rental_id')) {
            $query->where('rental_id', $request->rental_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->where('payment_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('payment_date', '<=', $request->end_date);
        }

        $payments = $query->with(['rental.property', 'rental.tenant'])
                         ->orderBy('payment_date', 'desc')
                         ->paginate(10);

        return response()->json($payments);
    }

    public function getPaymentStatistics(Request $request)
    {
        $query = Payment::query();

        if ($request->user()->role === 'tenant') {
            $query->whereHas('rental', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->id);
            });
        } elseif ($request->user()->role === 'landlord') {
            $query->whereHas('rental.property', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            });
        }

        $statistics = [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'completed_payments' => $query->where('status', 'completed')->count(),
            'pending_payments' => $query->where('status', 'pending')->count(),
            'failed_payments' => $query->where('status', 'failed')->count(),
            'average_payment' => $query->avg('amount')
        ];

        return response()->json($statistics);
    }
}