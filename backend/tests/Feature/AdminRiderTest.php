<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRiderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_grants_the_rider_role(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/customers/{$user->id}", ['is_rider' => true])
            ->assertOk()
            ->assertJsonPath('data.is_rider', true);

        $this->assertTrue($user->fresh()->is_rider);
    }

    public function test_update_customer_cannot_set_is_admin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/customers/{$user->id}", ['is_rider' => true, 'is_admin' => true])->assertOk();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_a_normal_user_cannot_grant_the_rider_role(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/admin/customers/1', ['is_rider' => true])->assertForbidden();
    }

    public function test_riders_endpoint_lists_only_riders(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'name' => 'Alex Rider']);
        User::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/riders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rider->id);
    }

    public function test_admin_assigns_a_rider_to_an_order(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'name' => 'Alex Rider']);
        $order = $this->order();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $rider->id])
            ->assertOk()
            ->assertJsonPath('data.delivery_partner_id', $rider->id)
            ->assertJsonPath('data.courier_name', 'Alex Rider');
    }

    public function test_switching_from_a_rider_to_a_typed_courier_name_keeps_the_name(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'name' => 'Alex Rider']);
        $order = $this->order(['delivery_partner_id' => $rider->id, 'courier_name' => 'Alex Rider']);
        Sanctum::actingAs($this->admin());

        // The "type a name + Save" button sends both keys.
        $this->patchJson("/api/admin/orders/{$order->id}", [
            'courier_name' => 'Contract Courier Co', 'delivery_partner_id' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.delivery_partner_id', null)
            ->assertJsonPath('data.courier_name', 'Contract Courier Co');
    }

    public function test_clearing_the_rider_alone_clears_the_courier_name(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'name' => 'Alex Rider']);
        $order = $this->order(['delivery_partner_id' => $rider->id, 'courier_name' => 'Alex Rider']);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => null])
            ->assertOk()
            ->assertJsonPath('data.courier_name', null);
    }

    public function test_assigning_a_non_rider_is_rejected(): void
    {
        $notRider = User::factory()->create();
        $order = $this->order();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $notRider->id])
            ->assertStatus(422);
    }

    public function test_admin_promotes_an_account_to_rider_by_email(): void
    {
        $user = User::factory()->create(['email' => 'newrider@example.com']);
        $store = $this->store('Main');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/riders', ['email' => 'newrider@example.com', 'store_ids' => [$store->id]])
            ->assertCreated()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.rider_is_active', true);

        $this->assertTrue($user->fresh()->is_rider);
        $this->assertTrue($user->fresh()->stores->contains($store));
    }

    public function test_hiring_a_rider_without_a_store_is_rejected(): void
    {
        User::factory()->create(['email' => 'newrider@example.com']);
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/riders', ['email' => 'newrider@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_ids');
    }

    public function test_promoting_an_unknown_email_is_rejected(): void
    {
        $store = $this->store('Main');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/riders', ['email' => 'nobody@example.com', 'store_ids' => [$store->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_admin_sets_a_riders_stores_base_and_shift_state(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $a = $this->store('Downtown');
        $b = $this->store('Uptown');
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/riders/{$rider->id}", [
            'phone' => '555-0100',
            'rider_base_lat' => 40.7128,
            'rider_base_lng' => -74.0060,
            'rider_is_active' => false,
            'store_ids' => [$a->id, $b->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.rider_is_active', false)
            ->assertJsonCount(2, 'data.stores');

        $rider->refresh();
        $this->assertEqualsWithDelta(40.7128, (float) $rider->rider_base_lat, 0.0001);
        $this->assertEqualsWithDelta(2, $rider->stores()->count(), 0);
    }

    public function test_base_address_is_geocoded_when_no_coordinates_are_given(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '40.7484', 'lon' => '-73.9857',
            'display_name' => '20 W 34th St, New York, NY', 'address' => ['city' => 'New York'],
        ]])]);

        $rider = User::factory()->create(['is_rider' => true]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/riders/{$rider->id}", ['rider_base_address' => '20 W 34th St, New York'])
            ->assertOk();

        $rider->refresh();
        $this->assertEqualsWithDelta(40.7484, (float) $rider->rider_base_lat, 0.0001);
        $this->assertEqualsWithDelta(-73.9857, (float) $rider->rider_base_lng, 0.0001);
    }

    public function test_cannot_edit_a_non_rider_through_the_rider_endpoint(): void
    {
        $user = User::factory()->create(); // not a rider
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/riders/{$user->id}", ['rider_is_active' => true])->assertNotFound();
    }

    public function test_removing_the_rider_role_detaches_stores(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $rider->stores()->attach($this->store('S')->id);
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/riders/{$rider->id}")->assertNoContent();

        $rider->refresh();
        $this->assertFalse($rider->is_rider);
        $this->assertSame(0, $rider->stores()->count());
    }

    public function test_rider_posts_its_live_location(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        Sanctum::actingAs($rider);

        $this->postJson('/api/rider/location', ['lat' => 40.7128, 'lng' => -74.0060])->assertOk();

        $rider->refresh();
        $this->assertEqualsWithDelta(40.7128, (float) $rider->rider_last_lat, 0.0001);
        $this->assertNotNull($rider->rider_last_located_at);
    }

    private function store(string $name): Store
    {
        return Store::create([
            'name' => $name, 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001',
            'latitude' => 40.7128, 'longitude' => -74.0060, 'delivery_radius_km' => 8, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(array $overrides = []): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id, 'status' => 'ready_for_delivery', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            ...$overrides,
        ]);
    }
}
