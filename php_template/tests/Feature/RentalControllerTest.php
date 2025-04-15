<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $landlord;
    protected $tenant;
    protected $property;
    protected $rental;

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
    }

    public function test_landlord_can_create_rental()
    {
        $newTenant = User::factory()->create(['role' => 'tenant']);
        
        $response = $this->actingAs($this->landlord)->postJson('/api/rentals', [
            'property_id' => $this->property->id,
            'tenant_id' => $newTenant->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'monthly_rent' => 1000,
            'security_deposit' => 1000,
            'lease_document_url' => 'https://example.com/lease.pdf',
            'payment_due_day' => 1
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'property_id',
                'tenant_id',
                'start_date',
                'end_date',
                'monthly_rent'
            ]);
    }

    public function test_tenant_can_view_own_rentals()
    {
        $response = $this->actingAs($this->tenant)
            ->getJson('/api/rentals');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'property_id',
                    'start_date',
                    'end_date'
                ]
            ]);
    }

    public function test_tenant_cannot_create_rental()
    {
        $response = $this->actingAs($this->tenant)
            ->postJson('/api/rentals', [
                'property_id' => $this->property->id,
                'tenant_id' => $this->tenant->id
            ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_update_rental()
    {
        $response = $this->actingAs($this->landlord)
            ->putJson("/api/rentals/{$this->rental->id}", [
                'monthly_rent' => 1200,
                'status' => 'active'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'monthly_rent' => 1200,
                'status' => 'active'
            ]);
    }

    public function test_landlord_can_terminate_rental()
    {
        $response = $this->actingAs($this->landlord)
            ->putJson("/api/rentals/{$this->rental->id}", [
                'status' => 'terminated'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'terminated'
            ]);
    }
}