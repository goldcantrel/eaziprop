<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $landlord;
    protected $tenant;
    protected $property;
    protected $rental;
    protected $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->tenant = User::factory()->create(['role' => 'tenant']);
        $this->property = Property::factory()->create([
            'landlord_id' => $this->landlord->id
        ]);
        $this->rental = Rental::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id
        ]);
        $this->payment = Payment::factory()->create([
            'rental_id' => $this->rental->id
        ]);
    }

    public function test_tenant_can_create_payment()
    {
        $response = $this->actingAs($this->tenant)->postJson('/api/payments', [
            'rental_id' => $this->rental->id,
            'amount' => 1000,
            'payment_method' => 'credit_card',
            'due_date' => '2024-01-01'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'payment' => [
                    'id',
                    'rental_id',
                    'amount',
                    'status'
                ],
                'client_secret'
            ]);
    }

    public function test_tenant_can_view_own_payments()
    {
        $response = $this->actingAs($this->tenant)
            ->getJson('/api/payments');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'amount',
                    'status',
                    'payment_date'
                ]
            ]);
    }

    public function test_landlord_can_view_property_payments()
    {
        $response = $this->actingAs($this->landlord)
            ->getJson('/api/payments');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'rental' => [
                        'id',
                        'property' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ]);
    }

    public function test_stripe_webhook_handles_successful_payment()
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'charges' => [
                        'data' => [
                            [
                                'id' => 'ch_test123'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->payment->update([
            'stripe_payment_intent_id' => 'pi_test123',
            'status' => 'pending'
        ]);

        $response = $this->postJson('/api/webhook/stripe', $payload, [
            'Stripe-Signature' => 'test_signature'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => 'completed'
        ]);
    }
}