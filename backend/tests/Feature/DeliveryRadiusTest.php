<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryRadiusTest extends TestCase
{
    use RefreshDatabase;

    // Times Square -> Empire State Building, a well-known ~1.06 km.
    public function test_haversine_matches_a_known_distance(): void
    {
        $km = Geo::haversineKm(40.7580, -73.9855, 40.7484, -73.9857);

        $this->assertEqualsWithDelta(1.06, $km, 0.05);
    }

    public function test_order_inside_the_store_radius_is_accepted(): void
    {
        $this->store(40.7580, -73.9855, 5);
        $this->shopAsCustomer();

        $this->postJson('/api/checkout', ['address' => $this->address(40.7484, -73.9857)])
            ->assertCreated();
    }

    public function test_order_outside_every_store_radius_is_rejected(): void
    {
        $this->store(40.7580, -73.9855, 5);
        $this->shopAsCustomer();

        // ~40 km north of the store.
        $this->postJson('/api/checkout', ['address' => $this->address(41.10, -73.9855)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address'])
            ->assertJsonPath('errors.address.0', "We don't deliver to your area yet — we're expanding fast and will reach you soon.");

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_accepted_if_within_range_of_any_store(): void
    {
        $this->store(40.7580, -73.9855, 2, 'Manhattan');
        $this->store(41.10, -73.9855, 5, 'Uptown');
        $this->shopAsCustomer();

        // Far from Manhattan (2km radius) but inside Uptown's 5km radius.
        $this->postJson('/api/checkout', ['address' => $this->address(41.12, -73.9855)])
            ->assertCreated();
    }

    public function test_a_closer_store_that_cannot_reach_does_not_shadow_one_that_can(): void
    {
        // Nearest store has a tiny radius and can't reach the customer; a store
        // slightly farther away has a wide radius that does.
        $this->store(40.7484, -73.9857, 1, 'Corner shop');
        $this->store(40.7000, -73.9857, 20, 'Regional hub');
        $this->shopAsCustomer();

        // ~1.6 km from the corner shop (radius 1), ~5.4 km from the hub (radius 20).
        $this->postJson('/api/checkout', ['address' => $this->address(40.7630, -73.9857)])
            ->assertCreated();
    }

    public function test_enforcement_can_be_turned_off(): void
    {
        config(['checkout.enforce_radius' => false]);
        $this->store(40.7580, -73.9855, 1);
        $this->shopAsCustomer();

        $this->postJson('/api/checkout', ['address' => $this->address(41.10, -73.9855)])
            ->assertCreated();
    }

    public function test_no_configured_store_does_not_block_checkout(): void
    {
        $this->shopAsCustomer();

        $this->postJson('/api/checkout', ['address' => $this->address(41.10, -73.9855)])
            ->assertCreated();
    }

    private function store(float $lat, float $lng, int $radiusKm, string $name = 'Test Store'): Store
    {
        return Store::create([
            'name' => $name, 'line1' => '1 Store St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => $lat, 'longitude' => $lng,
            'delivery_radius_km' => $radiusKm, 'is_active' => true,
        ]);
    }

    /** Sign in as a fresh customer with one item already in the cart. */
    private function shopAsCustomer(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'inventory_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
    }

    private function address(float $lat, float $lng): array
    {
        return [
            'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'New York',
            'state' => 'NY', 'postal_code' => '10002', 'latitude' => $lat, 'longitude' => $lng,
        ];
    }
}
