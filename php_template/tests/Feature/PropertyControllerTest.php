<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $landlord;
    protected $tenant;
    protected $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->tenant = User::factory()->create(['role' => 'tenant']);
        $this->property = Property::factory()->create([
            'landlord_id' => $this->landlord->id
        ]);
    }

    public function test_landlord_can_create_property()
    {
        $response = $this->actingAs($this->landlord)->postJson('/api/properties', [
            'name' => 'Test Property',
            'type' => 'apartment',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'country' => 'Test Country',
            'description' => 'Test Description',
            'monthly_rent' => 1000,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'square_feet' => 1000,
            'available_from' => '2024-01-01',
            'minimum_lease_period' => 12
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'address',
                'monthly_rent'
            ]);
    }

    public function test_landlord_can_view_own_properties()
    {
        $response = $this->actingAs($this->landlord)
            ->getJson('/api/properties');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'type',
                    'address'
                ]
            ]);
    }

    public function test_tenant_cannot_create_property()
    {
        $response = $this->actingAs($this->tenant)
            ->postJson('/api/properties', [
                'name' => 'Test Property',
                'type' => 'apartment'
            ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_update_own_property()
    {
        $response = $this->actingAs($this->landlord)
            ->putJson("/api/properties/{$this->property->id}", [
                'name' => 'Updated Property',
                'monthly_rent' => 1200
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Property',
                'monthly_rent' => 1200
            ]);
    }

    public function test_landlord_can_delete_own_property()
    {
        $response = $this->actingAs($this->landlord)
            ->deleteJson("/api/properties/{$this->property->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('properties', ['id' => $this->property->id]);
    }
}