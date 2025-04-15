<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $landlord;
    protected $tenant;
    protected $property;
    protected $maintenanceRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->tenant = User::factory()->create(['role' => 'tenant']);
        $this->property = Property::factory()->create([
            'landlord_id' => $this->landlord->id
        ]);
        $this->maintenanceRequest = MaintenanceRequest::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id
        ]);
    }

    public function test_tenant_can_create_maintenance_request()
    {
        $response = $this->actingAs($this->tenant)->postJson('/api/maintenance', [
            'property_id' => $this->property->id,
            'title' => 'Test Request',
            'description' => 'Test Description',
            'priority' => 'medium'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'property_id',
                'tenant_id',
                'title',
                'priority',
                'status'
            ]);
    }

    public function test_tenant_can_view_own_maintenance_requests()
    {
        $response = $this->actingAs($this->tenant)
            ->getJson('/api/maintenance');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'status',
                    'priority'
                ]
            ]);
    }

    public function test_landlord_can_update_maintenance_request()
    {
        $response = $this->actingAs($this->landlord)
            ->putJson("/api/maintenance/{$this->maintenanceRequest->id}", [
                'status' => 'in_progress',
                'assigned_to' => $this->landlord->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'in_progress',
                'assigned_to' => $this->landlord->id
            ]);
    }

    public function test_tenant_can_update_request_description()
    {
        $response = $this->actingAs($this->tenant)
            ->putJson("/api/maintenance/{$this->maintenanceRequest->id}", [
                'description' => 'Updated description'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'description' => 'Updated description'
            ]);
    }

    public function test_tenant_cannot_change_request_status()
    {
        $response = $this->actingAs($this->tenant)
            ->putJson("/api/maintenance/{$this->maintenanceRequest->id}", [
                'status' => 'completed'
            ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_mark_request_as_completed()
    {
        $response = $this->actingAs($this->landlord)
            ->putJson("/api/maintenance/{$this->maintenanceRequest->id}", [
                'status' => 'completed',
                'actual_cost' => 150.00
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'completed',
                'actual_cost' => 150.00
            ]);

        $this->assertNotNull(
            $this->maintenanceRequest->fresh()->completed_at
        );
    }
}