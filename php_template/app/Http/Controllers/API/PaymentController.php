<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function index()
    {
        $user = Auth::user();
        
        if ($user->role === 'superuser') {
            $payments = Payment::with(['rental.property', 'rental.tenant'])->get();
        } elseif ($user->role === 'landlord') {
            $payments = Payment::whereHas('rental.property', function($query) use ($user) {
                $query->where('landlord_id', $user->id);
            })->with(['rental.property', 'rental.tenant'])->get();
        } else {
            $payments = Payment::whereHas('rental', function($query) use ($user) {
                $query->where('tenant_id', $user->id);
            })->with(['rental.property'])->get();
        }

        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
            'payment_method' => 'required|in:credit_card,bank_transfer,cash,check'
        ]);

        $rental = Rental::findOrFail($request->rental_id);
        $this->authorize('create', [Payment::class, $rental]);

        // Create Stripe Payment Intent for credit card payments
        if ($request->payment_method === 'credit_card') {
            try {
                $paymentIntent = PaymentIntent::create([
                    'amount' => $request->amount * 100, // Convert to cents
                    'currency' => 'usd',
                    'metadata' => [
                        'rental_id' => $rental->id,
                        'property_id' => $rental->property_id
                    ]
                ]);

                $payment = Payment::create([
                    ...$request->validated(),
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'status' => 'pending'
                ]);

                return response()->json([
                    'payment' => $payment,
                    'client_secret' => $paymentIntent->client_secret
                ]);
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
        }

        // Handle other payment methods
        $payment = Payment::create([
            ...$request->validated(),
            'status' => $request->payment_method === 'cash' ? 'completed' : 'pending'
        ]);

        return response()->json($payment, 201);
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);
        return response()->json($payment->load('rental.property'));
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        $request->validate([
            'status' => 'required|in:pending,completed,failed,refunded',
            'payment_method' => 'in:credit_card,bank_transfer,cash,check',
            'transaction_id' => 'string',
            'notes' => 'string'
        ]);

        $payment->update($request->validated());
        return response()->json($payment);
    }

    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, config('services.stripe.webhook_secret')
            );

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
                    if ($payment) {
                        $payment->update([
                            'status' => 'completed',
                            'payment_date' => now(),
                            'transaction_id' => $paymentIntent->charges->data[0]->id
                        ]);
                    }
                    break;
                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
                    if ($payment) {
                        $payment->update(['status' => 'failed']);
                    }
                    break;
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}