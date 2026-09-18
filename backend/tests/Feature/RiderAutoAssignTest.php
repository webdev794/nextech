<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Support\RiderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAutoAssignTest extends TestCase
{
    use RefreshDatabase;

    private function store(float $lat = 40.7128, float $lng = -74.0060): Store
    {
        return Store::create([
            'name' => 'Hub', 'line1' => '1 Hub St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => $lat, 'longitude' => $lng,
            'delivery_radius_km' => 10, 'is_active' => true,
        ]);
    }

    private function rider(string $name, ?Store $store, ?array $base, int $activeDeliveries = 0): User
    {
        $rider = User::factory()->create([
            'name' => $name,
            'is_rider' => true,
            'rider_is_active' => true,
            'rider_available' => true,
            'rider_base_lat' => $base['lat'] ?? null,
            'rider_base_lng' => $base['lng'] ?? null,
        ]);

        if ($store) {
            $rider->stores()->attach($store->id);
        }

        for ($i = 0; $i < $activeDeliveries; $i++) {
            Order::create([
                'user_id' => User::factory()->create()->id,
                'delivery_partner_id' => $rider->id,
                'status' => 'out_for_delivery', 'payment_status' => 'paid',
                'subtotal_cents' => 100, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 100,
                'delivery_address' => ['name' => 'x', 'line1' => '1', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            ]);
        }

        return $rider;
    }

    private function order(Store $store): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'store_id' => $store->id,
            'status' => 'ready_for_delivery', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
        ]);
    }

    public function test_assigns_the_nearest_linked_rider(): void
    {
        $store = $this->store();
        $near = $this->rider('Near', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $this->rider('Far', $store, ['lat' => 40.7600, 'lng' => -73.9800]);

        $rider = RiderAssignment::assign($this->order($store));

        $this->assertSame($near->id, $rider?->id);
    }

    public function test_rider_not_linked_to_the_store_is_skipped(): void
    {
        $store = $this->store();
        $other = $this->store(41.0, -74.0);
        $this->rider('Wrong store', $other, ['lat' => 40.7128, 'lng' => -74.0060]); // right on the hub, wrong store
        $linked = $this->rider('Linked', $store, ['lat' => 40.80, 'lng' => -73.95]);

        $this->assertSame($linked->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_a_busy_rider_loses_to_a_slightly_farther_idle_one(): void
    {
        $store = $this->store();
        // Busy rider sits on the hub but already has 2 deliveries (2 * 6 km penalty).
        $this->rider('Busy', $store, ['lat' => 40.7128, 'lng' => -74.0060], activeDeliveries: 2);
        // Idle rider ~4 km away, no load.
        $idle = $this->rider('Idle', $store, ['lat' => 40.7480, 'lng' => -74.0060]);

        $this->assertSame($idle->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_no_eligible_rider_leaves_the_order_unassigned(): void
    {
        $store = $this->store();
        $this->rider('No location', $store, []); // linked but no base and no live fix

        $order = $this->order($store);
        $this->assertNull(RiderAssignment::assign($order));
        $this->assertNull($order->fresh()->delivery_partner_id);
    }

    public function test_inactive_rider_is_skipped(): void
    {
        $store = $this->store();
        $off = $this->rider('Off shift', $store, ['lat' => 40.7128, 'lng' => -74.0060]);
        $off->forceFill(['rider_is_active' => false])->save();
        $on = $this->rider('On shift', $store, ['lat' => 40.80, 'lng' => -73.95]);

        $this->assertSame($on->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_unavailable_rider_is_skipped(): void
    {
        $store = $this->store();
        $paused = $this->rider('Paused', $store, ['lat' => 40.7128, 'lng' => -74.0060]);
        $paused->forceFill(['rider_available' => false])->save();
        $ready = $this->rider('Ready', $store, ['lat' => 40.80, 'lng' => -73.95]);

        $this->assertSame($ready->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_live_location_wins_over_base_when_fresh(): void
    {
        $store = $this->store();
        // Base is far, but a fresh live ping puts them next to the hub.
        $rider = $this->rider('Roamer', $store, ['lat' => 41.5, 'lng' => -73.0]);
        $rider->forceFill([
            'rider_last_lat' => 40.7129, 'rider_last_lng' => -74.0061,
            'rider_last_located_at' => now()->subMinutes(2),
        ])->save();
        $this->rider('Home body', $store, ['lat' => 40.9, 'lng' => -73.9]);

        $this->assertSame($rider->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_stale_live_location_falls_back_to_base(): void
    {
        $store = $this->store();
        $rider = $this->rider('Stale', $store, ['lat' => 40.9, 'lng' => -73.9]);
        $rider->forceFill([
            'rider_last_lat' => 40.7129, 'rider_last_lng' => -74.0061,
            'rider_last_located_at' => now()->subHours(3), // stale — ignored
        ])->save();
        $close = $this->rider('Closer base', $store, ['lat' => 40.75, 'lng' => -74.0]);

        $this->assertSame($close->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_switch_off_disables_auto_assignment(): void
    {
        Setting::put('rider_auto_assign', false);
        $store = $this->store();
        $this->rider('Ready', $store, ['lat' => 40.7128, 'lng' => -74.0060]);

        $this->assertNull(RiderAssignment::assign($this->order($store)));
    }

    public function test_order_without_a_store_is_not_auto_assigned(): void
    {
        $store = $this->store();
        $this->rider('Ready', $store, ['lat' => 40.7128, 'lng' => -74.0060]);

        $order = $this->order($store);
        $order->forceFill(['store_id' => null])->save();

        $this->assertNull(RiderAssignment::assign($order->fresh()));
    }

    public function test_advancing_an_order_to_ready_for_delivery_auto_assigns_via_the_admin_endpoint(): void
    {
        $store = $this->store();
        $rider = $this->rider('Auto', $store, ['lat' => 40.7130, 'lng' => -74.0058]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'store_id' => $store->id,
            'status' => 'packing', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'ready_for_delivery'])
            ->assertOk()
            ->assertJsonPath('data.delivery_partner_id', $rider->id)
            ->assertJsonPath('data.courier_name', 'Auto');

        // Auto-assignment is a time-boxed offer, not yet an acceptance.
        $order->refresh();
        $this->assertNotNull($order->rider_offer_expires_at);
        $this->assertTrue($order->rider_offer_expires_at->isFuture());
        $this->assertNull($order->rider_accepted_at);
    }

    public function test_a_rider_already_assigned_by_the_admin_is_not_overridden(): void
    {
        $store = $this->store();
        $chosen = $this->rider('Chosen', $store, ['lat' => 40.90, 'lng' => -73.90]); // farther
        $this->rider('Nearer', $store, ['lat' => 40.7129, 'lng' => -74.0061]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'store_id' => $store->id,
            'status' => 'packing', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'ready_for_delivery',
            'delivery_partner_id' => $chosen->id,
        ])->assertOk()->assertJsonPath('data.delivery_partner_id', $chosen->id);
    }
}
