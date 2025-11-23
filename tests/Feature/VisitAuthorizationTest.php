<?php

namespace Tests\Feature;

use Tests\TestCase;
use Modules\Visit\App\Models\Visit;
use Modules\Admin\App\Models\Admin;
use Modules\Agent\App\Models\Agent;
use Modules\Client\App\Models\Client;
use Modules\Logement\App\Models\Logement;
use Modules\Agency\App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class VisitAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup test data
    }

    /** @test */
    public function unauthenticated_user_cannot_access_visits()
    {
        $response = $this->getJson('/api/visits');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function admin_global_can_view_all_visits()
    {
        $admin = Admin::factory()->create();
        $token = JWTAuth::fromUser($admin);
        
        $visit = Visit::factory()->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/visits');
        
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'visits']);
    }

    /** @test */
    public function agent_can_only_view_visits_from_their_agency()
    {
        $agency1 = Agency::factory()->create();
        $agency2 = Agency::factory()->create();
        
        $agent = Agent::factory()->create([
            'agency_id' => $agency1->_id,
            'role' => 'agent',
        ]);
        
        $logement1 = Logement::factory()->create(['agency_id' => $agency1->_id]);
        $logement2 = Logement::factory()->create(['agency_id' => $agency2->_id]);
        
        $visit1 = Visit::factory()->create(['logement_id' => $logement1->_id]);
        $visit2 = Visit::factory()->create(['logement_id' => $logement2->_id]);
        
        $token = JWTAuth::fromUser($agent);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/visits');
        
        $response->assertStatus(200);
        $visits = $response->json('visits');
        
        // Should only see visit1, not visit2
        $this->assertCount(1, $visits);
        $this->assertEquals($visit1->_id, $visits[0]['_id']);
    }

    /** @test */
    public function client_can_only_view_their_own_visits()
    {
        $client1 = Client::factory()->create();
        $client2 = Client::factory()->create();
        
        $visit1 = Visit::factory()->create(['client_id' => $client1->_id]);
        $visit2 = Visit::factory()->create(['client_id' => $client2->_id]);
        
        $token = JWTAuth::fromUser($client1);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/visits');
        
        $response->assertStatus(200);
        $visits = $response->json('visits');
        
        // Should only see visit1
        $this->assertCount(1, $visits);
        $this->assertEquals($visit1->_id, $visits[0]['_id']);
    }

    /** @test */
    public function agent_cannot_view_visit_from_different_agency()
    {
        $agency1 = Agency::factory()->create();
        $agency2 = Agency::factory()->create();
        
        $agent = Agent::factory()->create([
            'agency_id' => $agency1->_id,
            'role' => 'agent',
        ]);
        
        $logement2 = Logement::factory()->create(['agency_id' => $agency2->_id]);
        $visit = Visit::factory()->create(['logement_id' => $logement2->_id]);
        
        $token = JWTAuth::fromUser($agent);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/visits/{$visit->_id}");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function agent_personnel_can_create_visit()
    {
        $agency = Agency::factory()->create();
        $agent = Agent::factory()->create([
            'agency_id' => $agency->_id,
            'role' => 'agent',
        ]);
        
        $logement = Logement::factory()->create(['agency_id' => $agency->_id]);
        $client = Client::factory()->create();
        
        $token = JWTAuth::fromUser($agent);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/visits', [
            'client_id' => $client->_id,
            'logement_id' => $logement->_id,
            'visit_date' => now()->toDateTimeString(),
        ]);
        
        $response->assertStatus(201);
    }

    /** @test */
    public function agent_rh_cannot_create_visit()
    {
        $agency = Agency::factory()->create();
        $agent = Agent::factory()->create([
            'agency_id' => $agency->_id,
            'role' => 'rh',
        ]);
        
        $logement = Logement::factory()->create(['agency_id' => $agency->_id]);
        $client = Client::factory()->create();
        
        $token = JWTAuth::fromUser($agent);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/visits', [
            'client_id' => $client->_id,
            'logement_id' => $logement->_id,
            'visit_date' => now()->toDateTimeString(),
        ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function client_can_update_their_own_visit()
    {
        $client = Client::factory()->create();
        $visit = Visit::factory()->create(['client_id' => $client->_id]);
        
        $token = JWTAuth::fromUser($client);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/visits/{$visit->_id}", [
            'visit_date' => now()->addDay()->toDateTimeString(),
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function client_cannot_update_other_client_visit()
    {
        $client1 = Client::factory()->create();
        $client2 = Client::factory()->create();
        $visit = Visit::factory()->create(['client_id' => $client2->_id]);
        
        $token = JWTAuth::fromUser($client1);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/visits/{$visit->_id}", [
            'visit_date' => now()->addDay()->toDateTimeString(),
        ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_global_can_delete_any_visit()
    {
        $admin = Admin::factory()->create();
        $visit = Visit::factory()->create();
        
        $token = JWTAuth::fromUser($admin);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/visits/{$visit->_id}");
        
        $response->assertStatus(200);
        $this->assertDatabaseMissing('visits', ['_id' => $visit->_id]);
    }
}

