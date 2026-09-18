<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryEtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_no_store_configured_everywhere_is_deliverable(): void
    {
        $this->getJson('/api/delivery-eta?lat=40.7484&lng=-73.9857')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.deliverable', true);
    }

    public function test_a_point_inside_the_radius_is_deliverable_with_an_eta(): void
    {
        $this->store(40.7580, -73.9855, 5);

        $response = $this->getJson('/api/delivery-eta?lat=40.7484&lng=-73.9857')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.deliverable', true);

        $this->assertGreaterThan(0, $response->json('data.minutes'));
    }

    public function test_a_point_outside_every_radius_is_not_deliverable(): void
    {
        $this->store(40.7580, -73.9855, 5);

        $this->getJson('/api/delivery-eta?lat=41.10&lng=-73.9855')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.deliverable', false);
    }

    private function store(float $lat, float $lng, int $radiusKm): Store
    {
        return Store::create([
            'name' => 'Test Store', 'line1' => '1 Store St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => $lat, 'longitude' => $lng,
            'delivery_radius_km' => $radiusKm, 'is_active' => true,
        ]);
    }
}
