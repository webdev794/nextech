<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Support\CheckoutFees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutFeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Setting::get is cached
    }

    public function test_distance_fee_interpolates_linearly_between_near_and_far(): void
    {
        Setting::put('checkout_fees', [
            'delivery_mode' => 'distance',
            'delivery_near_fee_cents' => 200,
            'delivery_far_fee_cents' => 600,
            'free_delivery_threshold_cents' => 100000,
        ]);
        $fees = CheckoutFees::current();

        $this->assertSame(200, CheckoutFees::distanceFeeCents($fees, 0.0, 10.0));   // at the store
        $this->assertSame(600, CheckoutFees::distanceFeeCents($fees, 10.0, 10.0));  // at the edge
        $this->assertSame(400, CheckoutFees::distanceFeeCents($fees, 5.0, 10.0));   // halfway
        $this->assertSame(600, CheckoutFees::distanceFeeCents($fees, 25.0, 10.0));  // clamped past the edge
        $this->assertSame(600, CheckoutFees::distanceFeeCents($fees, null, null));  // no coordinates -> far
    }

    public function test_fixed_mode_ignores_distance(): void
    {
        Setting::put('checkout_fees', ['delivery_mode' => 'fixed', 'delivery_fee_cents' => 350]);
        $fees = CheckoutFees::current();

        $this->assertSame(350, CheckoutFees::distanceFeeCents($fees, 0.0, 10.0));
        $this->assertSame(350, CheckoutFees::distanceFeeCents($fees, 9.0, 10.0));
    }

    public function test_free_delivery_threshold_waives_the_fee(): void
    {
        Setting::put('checkout_fees', [
            'delivery_mode' => 'distance', 'delivery_near_fee_cents' => 200,
            'delivery_far_fee_cents' => 600, 'free_delivery_threshold_cents' => 3500,
        ]);
        $fees = CheckoutFees::current();

        $this->assertSame(0, CheckoutFees::deliveryFeeCents($fees, 4000, 5.0, 10.0));
        $this->assertGreaterThan(0, CheckoutFees::deliveryFeeCents($fees, 3400, 5.0, 10.0));
    }

    public function test_checkout_charges_the_near_fee_at_the_store_in_distance_mode(): void
    {
        Setting::put('checkout_fees', [
            'delivery_mode' => 'distance',
            'delivery_near_fee_cents' => 250,
            'delivery_far_fee_cents' => 900,
            'free_delivery_threshold_cents' => 100000,
            'handling_fee_cents' => 99,
            'small_cart_fee_cents' => 199,
            'small_cart_min_cents' => 1000,
        ]);

        Store::create([
            'name' => 'HQ', 'line1' => '1 Store St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7580, 'longitude' => -73.9855,
            'delivery_radius_km' => 10, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'price_cents' => 1500, 'inventory_quantity' => 5]);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        // Deliver to the store's own coordinates -> distance ~0 -> the near fee.
        $this->postJson('/api/checkout', ['address' => [
            'name' => 'Test', 'line1' => '1 Store St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7580, 'longitude' => -73.9855,
        ]])->assertCreated()
            ->assertJsonPath('data.delivery_fee_cents', 250)
            ->assertJsonPath('data.handling_fee_cents', 99)
            ->assertJsonPath('data.small_cart_fee_cents', 0);
    }

    public function test_admin_can_change_the_fee_config_and_it_shows_in_public_endpoints(): void
    {
        Store::create([
            'name' => 'HQ', 'line1' => '1 Store St', 'city' => 'NY', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7580, 'longitude' => -73.9855,
            'delivery_radius_km' => 8, 'is_active' => true,
        ]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson('/api/admin/settings', [
            'delivery_mode' => 'distance',
            'delivery_near_fee_cents' => 199,
            'delivery_far_fee_cents' => 799,
            'handling_fee_cents' => 149,
        ])->assertOk()
            ->assertJsonPath('data.delivery_mode', 'distance')
            ->assertJsonPath('data.delivery_far_fee_cents', 799);

        $this->getJson('/api/config')
            ->assertJsonPath('data.delivery_mode', 'distance')
            ->assertJsonPath('data.handling_fee_cents', 149);

        $eta = $this->getJson('/api/delivery-eta?lat=40.7600&lng=-73.9855')->assertOk();
        $eta->assertJsonPath('data.delivery_mode', 'distance');
        $this->assertGreaterThanOrEqual(199, $eta->json('data.delivery_fee_cents'));
        $this->assertLessThanOrEqual(799, $eta->json('data.delivery_fee_cents'));
    }

    public function test_a_normal_user_cannot_change_fee_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/admin/settings', ['delivery_fee_cents' => 1])->assertForbidden();
    }

    public function test_an_invalid_delivery_mode_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson('/api/admin/settings', ['delivery_mode' => 'sideways'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_mode']);
    }
}
