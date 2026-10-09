<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Support\NeedsCourier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NeedsCourierTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs): Order
    {
        return Order::query()->forceCreate(array_merge(['user_id' => User::factory()->create()->id, 'market' => 'US', 'payment_method' => 'card', 'payment_status' => 'paid', 'subtotal_cents' => 2500, 'total_cents' => 2500, 'delivery_address' => ['line1' => 'x']], $attrs));
    }

    public function test_order_no_rider_takes_in_time_is_flagged_and_admin_emailed(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $order = $this->order(['status' => 'ready_for_delivery', 'delivery_method' => 'own_rider']);

        $this->assertSame(0, NeedsCourier::sweep()); // still within 5 hours
        $this->travel(6)->hours();
        $this->assertSame(1, NeedsCourier::sweep());
        $this->assertSame('no_rider', $order->fresh()->needs_courier_reason);
        Notification::assertSentTo($admin, AdminNotice::class);
        $this->assertSame(0, NeedsCourier::sweep()); // flagged once

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/orders?status=needs_courier')->assertOk()->assertJsonPath('data.0.id', $order->id);
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonPath('data.needs_courier.0.id', $order->id);

        // A rider accepts it after all: no longer needs a courier.
        $order->fresh()->update(['rider_accepted_at' => now()]);
        $this->assertNull($order->fresh()->needs_courier_at);
    }

    public function test_outside_area_order_without_courier_account_is_flagged_not_test_booked(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $order = $this->order(['status' => 'packing', 'delivery_method' => 'online_courier']);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'ready_for_delivery'])->assertOk();
        $fresh = $order->fresh();
        $this->assertSame('ready_for_delivery', $fresh->status);
        $this->assertSame('outside_area', $fresh->needs_courier_reason);
        $this->assertNull($fresh->shipment);
    }

    public function test_offer_shows_to_the_stores_other_riders_and_first_to_take_it_gets_it(): void
    {
        $store = \App\Models\Store::query()->forceCreate(['name' => 'Hub', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'country' => 'US', 'delivery_radius_km' => 5, 'is_active' => true]);
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        foreach ([$a, $b] as $r) {
            $r->forceFill(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true])->save();
            $r->stores()->attach($store->id);
        }
        $order = $this->order(['status' => 'ready_for_delivery', 'delivery_method' => 'own_rider', 'store_id' => $store->id,
            'delivery_partner_id' => $a->id, 'rider_offer_expires_at' => now()->addHours(5)]);

        // B sees it with A's countdown and takes it first; A can no longer accept.
        Sanctum::actingAs($b);
        $this->getJson('/api/rider/orders')->assertOk()->assertJsonPath('data.pool.0.id', $order->id)->assertJsonPath('data.pool.0.offer_pending', true);
        $this->postJson("/api/rider/orders/{$order->id}/claim")->assertOk();
        $this->assertSame($b->id, $order->fresh()->delivery_partner_id);
        Sanctum::actingAs($a);
        $this->postJson("/api/rider/orders/{$order->id}/claim")->assertStatus(422);
        $this->postJson("/api/rider/orders/{$order->id}/respond", ['accept' => true])->assertStatus(409);
    }

    public function test_days_off_lists_weekends_and_holidays_the_seller_does_not_work(): void
    {
        $shop = new \App\Models\Shop(['market' => 'US']);
        $shop->forceFill(['ships_saturday' => true, 'ships_sunday' => false, 'working_holidays' => []]);
        // Fri 25 Dec 2026 (Christmas) → Mon 28 Dec: Christmas and Sunday off, Saturday worked.
        $off = \App\Support\SellerShipping::daysOff($shop, \Illuminate\Support\Carbon::parse('2026-12-24'), \Illuminate\Support\Carbon::parse('2026-12-28'));
        $this->assertCount(2, $off);
        $this->assertStringContainsString('25 Dec', $off[0]);
        $this->assertSame('Sun 27 Dec', $off[1]);
    }
}
