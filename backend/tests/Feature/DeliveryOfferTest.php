<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Support\DeliveryOfferSweeper;
use App\Support\RiderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryOfferTest extends TestCase
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

    private function rider(string $name, Store $store, array $base): User
    {
        $rider = User::factory()->create([
            'name' => $name, 'is_rider' => true, 'rider_is_active' => true, 'rider_available' => true,
            'rider_base_lat' => $base['lat'], 'rider_base_lng' => $base['lng'],
        ]);
        $rider->stores()->attach($store->id);

        return $rider;
    }

    private function order(Store $store, array $overrides = []): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'store_id' => $store->id,
            'status' => 'ready_for_delivery', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
            ...$overrides,
        ]);
    }

    public function test_assign_creates_an_offer_not_a_firm_assignment(): void
    {
        $store = $this->store();
        $rider = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);

        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->refresh();

        $this->assertSame($rider->id, $order->delivery_partner_id);
        $this->assertNotNull($order->rider_offer_expires_at);
        $this->assertTrue($order->rider_offer_expires_at->isFuture());
        $this->assertNull($order->rider_accepted_at);

        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/orders')
            ->assertOk()
            ->assertJsonPath('data.assigned.0.offer_pending', true)
            ->assertJsonPath('data.assigned.0.accepted', false);
    }

    public function test_rider_accepts_an_offer(): void
    {
        $store = $this->store();
        $rider = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => true])->assertOk();

        $order->refresh();
        $this->assertNotNull($order->rider_accepted_at);
        $this->assertNull($order->rider_offer_expires_at);
        $this->assertSame($rider->id, $order->delivery_partner_id);
        $this->assertSame(0, $rider->fresh()->rider_declined_count);
    }

    public function test_rider_rejects_and_the_order_is_re_offered_to_the_next_best(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]); // nearer
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $this->assertSame($a->id, $order->fresh()->delivery_partner_id);

        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => false])->assertOk();

        $order->refresh();
        $this->assertSame($b->id, $order->delivery_partner_id);
        $this->assertSame([$a->id], $order->rider_offer_declined_ids);
        $this->assertSame(1, $order->rider_offer_decline_count);
        $this->assertSame(1, $a->fresh()->rider_declined_count);
        $this->assertTrue($order->rider_offer_expires_at->isFuture());
    }

    public function test_reject_with_no_other_rider_falls_to_the_pool(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);

        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => false])->assertOk();

        $order->refresh();
        $this->assertNull($order->delivery_partner_id);
        $this->assertNull($order->rider_offer_expires_at);
        $this->assertSame('ready_for_delivery', $order->status);
        $this->assertSame(1, $order->rider_offer_decline_count);
        $this->assertSame(1, $a->fresh()->rider_declined_count);
    }

    public function test_offer_timeout_reassigns_and_bumps_missed_count(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill(['rider_offer_expires_at' => now()->subSecond()])->save();

        $this->assertSame(1, DeliveryOfferSweeper::sweep());

        $order->refresh();
        $this->assertSame($b->id, $order->delivery_partner_id);
        $this->assertSame(1, $a->fresh()->rider_missed_count);
        $this->assertSame(0, $a->fresh()->rider_declined_count);
        $this->assertContains($a->id, $order->rider_offer_declined_ids);
    }

    public function test_timeout_with_no_other_rider_falls_to_the_pool(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill(['rider_offer_expires_at' => now()->subSecond()])->save();

        DeliveryOfferSweeper::sweep();

        $order->refresh();
        $this->assertNull($order->delivery_partner_id);
        $this->assertNull($order->rider_offer_expires_at);
        $this->assertSame(1, $a->fresh()->rider_missed_count);
    }

    public function test_sweep_ignores_an_accepted_order(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill([
            'rider_accepted_at' => now(),
            'rider_offer_expires_at' => now()->subMinute(),
        ])->save();

        $this->assertSame(0, DeliveryOfferSweeper::sweep());
        $this->assertSame($a->id, $order->fresh()->delivery_partner_id);
        $this->assertSame(0, $a->fresh()->rider_missed_count);
    }

    public function test_sweep_clears_a_stale_marker_on_a_picked_up_order(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store, [
            'status' => 'out_for_delivery',
            'delivery_partner_id' => $a->id,
            'rider_offer_expires_at' => now()->subMinute(),
        ]);

        DeliveryOfferSweeper::sweep();

        $order->refresh();
        $this->assertNull($order->rider_offer_expires_at);
        $this->assertSame($a->id, $order->delivery_partner_id);
        $this->assertSame(0, $a->fresh()->rider_missed_count);
    }

    public function test_accept_after_a_sweep_reassigned_it_returns_409(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill(['rider_offer_expires_at' => now()->subSecond()])->save();
        DeliveryOfferSweeper::sweep();
        $this->assertSame($b->id, $order->fresh()->delivery_partner_id);

        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => true])->assertStatus(409);
        $this->assertSame($b->id, $order->fresh()->delivery_partner_id);
    }

    public function test_claim_from_the_pool_auto_accepts(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store); // no partner — sitting in the pool

        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/claim")->assertOk();

        $order->refresh();
        $this->assertNotNull($order->rider_accepted_at);
        $this->assertNull($order->rider_offer_expires_at);
        $this->assertSame('out_for_delivery', $order->status);
    }

    public function test_manual_admin_assignment_at_ready_starts_the_offer_clock(): void
    {
        $store = $this->store();
        $rider = $this->rider('A', $store, ['lat' => 40.90, 'lng' => -73.90]);
        $order = $this->order($store);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $rider->id])->assertOk();

        $order->refresh();
        $this->assertNotNull($order->rider_offer_expires_at);
        $this->assertTrue($order->rider_offer_expires_at->isFuture());
        $this->assertNull($order->rider_accepted_at);
    }

    public function test_manual_admin_assignment_during_packing_stays_a_soft_assignment(): void
    {
        $store = $this->store();
        $rider = $this->rider('A', $store, ['lat' => 40.90, 'lng' => -73.90]);
        $order = $this->order($store, ['status' => 'packing']);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $rider->id])->assertOk();

        $order->refresh();
        $this->assertSame($rider->id, $order->delivery_partner_id);
        $this->assertNull($order->rider_offer_expires_at);

        // Advancing it to ready now starts the clock.
        $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'ready_for_delivery'])->assertOk();
        $this->assertNotNull($order->fresh()->rider_offer_expires_at);
    }

    public function test_resaving_the_same_rider_preserves_acceptance(): void
    {
        $store = $this->store();
        $rider = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => true])->assertOk();
        $acceptedAt = $order->fresh()->rider_accepted_at;

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $rider->id])->assertOk();

        $order->refresh();
        $this->assertEquals($acceptedAt->timestamp, $order->rider_accepted_at->timestamp);
        $this->assertNull($order->rider_offer_expires_at);
    }

    public function test_admin_clearing_the_rider_clears_the_offer(): void
    {
        $store = $this->store();
        $rider = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => null])->assertOk();

        $order->refresh();
        $this->assertNull($order->delivery_partner_id);
        $this->assertNull($order->rider_offer_expires_at);
        $this->assertNull($order->rider_accepted_at);
    }

    public function test_lazy_sweep_runs_on_the_rider_orders_endpoint(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill(['rider_offer_expires_at' => now()->subSecond()])->save();

        Sanctum::actingAs($b);
        $this->getJson('/api/rider/orders')
            ->assertOk()
            ->assertJsonPath('data.assigned.0.id', $order->id)
            ->assertJsonPath('data.assigned.0.offer_pending', true);
        $this->assertSame(1, $a->fresh()->rider_missed_count);
    }

    public function test_respond_requires_ownership(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $c = $this->rider('C', $store, ['lat' => 40.90, 'lng' => -73.90]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $this->assertSame($a->id, $order->fresh()->delivery_partner_id);

        Sanctum::actingAs($c);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => true])->assertStatus(409);
    }

    public function test_respond_validates_accept(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $order = $this->order($store);
        RiderAssignment::assign($order);

        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/respond", [])->assertStatus(422);
    }

    public function test_decline_count_accumulates_across_riders(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);

        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => false])->assertOk();
        Sanctum::actingAs($b);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => false])->assertOk();

        $order->refresh();
        $this->assertSame(2, $order->rider_offer_decline_count);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $order->rider_offer_declined_ids);
        $this->assertNull($order->delivery_partner_id);
    }

    public function test_offer_counters_feed_the_acceptance_rate(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);

        // Offer 1: A rejects -> re-offered to B, B accepts.
        $o1 = $this->order($store);
        RiderAssignment::assign($o1);
        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$o1->id}/respond", ['accept' => false])->assertOk();
        Sanctum::actingAs($b);
        $this->postJson("/api/rider/orders/{$o1->id}/respond", ['accept' => true])->assertOk();

        // Offer 2: goes straight to A (B now busy/farther), A accepts.
        $o2 = $this->order($store);
        RiderAssignment::assign($o2->fresh());
        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$o2->id}/respond", ['accept' => true])->assertOk();

        $a->refresh();
        $this->assertSame(2, $a->rider_offers_count);   // offered o1 and o2
        $this->assertSame(1, $a->rider_declined_count);
        $this->assertSame(0, $a->rider_missed_count);
        $this->assertSame(0.5, $a->riderAcceptanceRate());

        Sanctum::actingAs($a);
        $this->getJson('/api/rider/stats')
            ->assertOk()
            ->assertJsonPath('data.offers_total', 2)
            ->assertJsonPath('data.declined_total', 1)
            ->assertJsonPath('data.acceptance_rate', 0.5);
    }

    public function test_timeout_counts_as_a_missed_offer_in_the_rate(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill(['rider_offer_expires_at' => now()->subSecond()])->save();
        DeliveryOfferSweeper::sweep();

        $a->refresh();
        $this->assertSame(1, $a->rider_offers_count);
        $this->assertSame(1, $a->rider_missed_count);
        $this->assertSame(0.0, $a->riderAcceptanceRate());
    }

    public function test_sweep_offers_command_runs(): void
    {
        $store = $this->store();
        $a = $this->rider('A', $store, ['lat' => 40.7130, 'lng' => -74.0058]);
        $b = $this->rider('B', $store, ['lat' => 40.7600, 'lng' => -73.9800]);
        $order = $this->order($store);
        RiderAssignment::assign($order);
        $order->forceFill(['rider_offer_expires_at' => now()->subSecond()])->save();

        $this->artisan('riders:sweep-offers')->assertExitCode(0);
        $this->assertSame($b->id, $order->fresh()->delivery_partner_id);
    }
}
