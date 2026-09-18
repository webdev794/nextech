<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_store_with_explicit_coordinates_and_radius(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/stores', [
            'name' => 'Downtown', 'line1' => '1 Market St', 'city' => 'New York',
            'state' => 'NY', 'postal_code' => '10001',
            'latitude' => 40.7128, 'longitude' => -74.0060,
            'delivery_radius_km' => 8, 'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.delivery_radius_km', 8)
            ->assertJsonPath('data.latitude', 40.7128);

        $this->assertDatabaseHas('stores', ['name' => 'Downtown', 'delivery_radius_km' => 8]);
    }

    public function test_admin_can_update_a_store_radius_and_delete_it(): void
    {
        $store = $this->store();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/stores/{$store->id}", [
            'line1' => $store->line1, 'city' => $store->city, 'state' => $store->state,
            'postal_code' => $store->postal_code, 'latitude' => $store->latitude,
            'longitude' => $store->longitude, 'delivery_radius_km' => 15,
        ])->assertOk()->assertJsonPath('data.delivery_radius_km', 15);

        $this->deleteJson("/api/admin/stores/{$store->id}")->assertNoContent();
        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
    }

    public function test_blank_coordinates_are_geocoded_from_the_address(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '51.5074', 'lon' => '-0.1278'],
        ])]);
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/stores', [
            'name' => 'London', 'line1' => '10 Downing St', 'city' => 'London',
            'state' => 'London', 'postal_code' => 'SW1A2AA', 'delivery_radius_km' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.latitude', 51.5074)
            ->assertJsonPath('data.longitude', -0.1278);
    }

    public function test_a_non_admin_cannot_manage_stores(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/stores')->assertForbidden();
        $this->postJson('/api/admin/stores', [
            'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001',
        ])->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function store(): Store
    {
        return Store::create([
            'name' => 'Test Store', 'line1' => '1 Store St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7580, 'longitude' => -73.9855,
            'delivery_radius_km' => 5, 'is_active' => true,
        ]);
    }
}
