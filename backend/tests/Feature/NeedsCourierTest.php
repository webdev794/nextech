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

    public function test_routine_emails_can_be_turned_off_but_important_ones_always_go(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->patchJson('/api/me/email-routine', ['on' => false])->assertOk();
        $user->refresh();
        $this->assertSame([], (new \App\Notifications\RiderNotice('Time to clock in', 'x', true))->via($user));
        $this->assertSame(['mail'], (new \App\Notifications\RiderNotice('Paused', 'x'))->via($user));
        $this->assertSame([], (new \App\Notifications\SellerNotice('Riders not on duty', 'x', true))->via($user));
        $this->assertSame(['mail'], (new \App\Notifications\AdminNotice('Order needs a courier', 'x'))->via($user));
        $this->getJson('/api/user')->assertOk()->assertJsonPath('email_routine', false);
    }

    public function test_performance_watch_warns_admin_about_poorly_rated_riders_once_a_week(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $rider = User::factory()->create(['name' => 'Slow Sam']);
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        foreach (range(1, 5) as $i) {
            $o = $this->order(['status' => 'completed', 'delivery_method' => 'own_rider']);
            \App\Models\RiderReview::create(['order_id' => $o->id, 'rider_id' => $rider->id, 'user_id' => $o->user_id, 'rating' => 2, 'source' => 'order']);
        }
        $this->assertSame(1, \App\Support\PerformanceWatch::check());
        $this->assertSame(0, \App\Support\PerformanceWatch::check()); // not repeated within a week
        Notification::assertSentTo($admin, AdminNotice::class);
        Sanctum::actingAs($admin);
        $id = $this->getJson('/api/admin/notifications')->assertOk()->assertJsonPath('data.performance_warnings.0.kind', 'rider')->json('data.performance_warnings.0.id');
        $this->deleteJson("/api/admin/performance-warnings/{$id}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_replies_in_buyer_chats_are_signed_with_a_support_name(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $buyer = User::factory()->create();
        $thread = \App\Models\SupportThread::create(['user_id' => $buyer->id, 'issue_type' => 'other', 'status' => 'open']);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/settings', ['support_agents' => ['Nina', 'Omar']])->assertOk();
        $this->postJson("/api/admin/support/threads/{$thread->id}/messages", ['body' => 'Hi, checking now'])->assertOk();
        $this->assertContains($thread->messages()->where('body', 'Hi, checking now')->value('agent_name'), ['Nina', 'Omar']);
        $this->postJson("/api/admin/support/threads/{$thread->id}/agent", ['agent' => 'Omar'])->assertOk();
        $this->postJson("/api/admin/support/threads/{$thread->id}/messages", ['body' => 'Done'])->assertOk();
        $this->assertSame('Omar', $thread->messages()->where('body', 'Done')->value('agent_name'));
    }

    public function test_monthly_pay_with_target_bonus_cap_and_top_up_pool(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $plan = ['monthly_cents' => 100000, 'target' => 4, 'bonus_per_extra_cents' => 5000, 'bonus_cap_cents' => 5000];
        [$busy, $slow] = [User::factory()->create(['name' => 'Busy']), User::factory()->create(['name' => 'Slow'])];
        foreach ([$busy, $slow] as $r) {
            $r->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        }
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/riders/{$busy->id}/pay-plan", ['plan' => $plan])->assertOk();
        $this->patchJson("/api/admin/riders/{$slow->id}/pay-plan", ['plan' => $plan])->assertOk();

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-15 12:00'));
        foreach ([[$busy, 7], [$slow, 2]] as [$r, $n]) {
            foreach (range(1, $n) as $i) {
                $o = $this->order(['status' => 'completed', 'delivery_method' => 'own_rider', 'delivery_partner_id' => $r->id, 'delivered_at' => now()]);
                \App\Support\RiderLedger::creditForDelivery($o); // monthly pay: no per-delivery credit
            }
        }
        $this->assertSame(0, (int) \App\Models\RiderLedgerEntry::where('type', 'delivery_credit')->count());

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 04:30'));
        $this->assertSame(2, \App\Support\RiderPayPlan::payMonth());
        $this->assertSame(0, \App\Support\RiderPayPlan::payMonth()); // once a month
        // Busy: 7 of 4 → full 1000 + bonus capped at 50 (3 extra × 50 = 150 → 100 to the pool). Slow: 2 of 4 → 500.
        $this->assertSame(105000, (int) \App\Models\RiderLedgerEntry::where('user_id', $busy->id)->sum('amount_cents'));
        $this->assertSame(50000, (int) \App\Models\RiderLedgerEntry::where('user_id', $slow->id)->sum('amount_cents'));
        $pool = $this->getJson('/api/admin/rider-pools')->assertOk()->json('data.0');
        $this->assertSame(10000, $pool['pool_cents']);
        $this->postJson('/api/admin/rider-pools/top-up', ['pool_id' => $pool['id'], 'rider_id' => $slow->id, 'amount_cents' => 10000])->assertOk()->assertJsonPath('data.0.pool_cents', 0);
        $this->assertSame(60000, (int) \App\Models\RiderLedgerEntry::where('user_id', $slow->id)->sum('amount_cents'));
    }

    public function test_weekly_pay_takes_off_missed_days(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/riders/{$rider->id}/pay-plan", ['plan' => ['period' => 'week', 'monthly_cents' => 30000, 'target' => 2, 'bonus_per_extra_cents' => 0, 'bonus_cap_cents' => 0, 'missed_day_cents' => 4000]])
            ->assertOk()->assertJsonPath('data.period', 'week');

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00')); // a Wednesday
        foreach (range(1, 2) as $i) {
            $this->order(['status' => 'completed', 'delivery_method' => 'own_rider', 'delivery_partner_id' => $rider->id, 'delivered_at' => now()]);
        }
        \App\Models\RiderLeave::create(['user_id' => $rider->id, 'date' => '2026-10-08', 'kind' => 'absent', 'status' => 'approved', 'told_ahead' => false]);

        $this->assertSame(0, \App\Support\RiderPayPlan::payMonth()); // weekly riders aren't paid monthly
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-12 04:40')); // Monday
        $this->assertSame(1, \App\Support\RiderPayPlan::payWeek());
        $this->assertSame(0, \App\Support\RiderPayPlan::payWeek()); // once a week
        // 2 of 2 → full 300, less 1 missed day × 40.
        $this->assertSame(26000, (int) \App\Models\RiderLedgerEntry::where('user_id', $rider->id)->sum('amount_cents'));
        $this->assertStringContainsString('1 missed day', \App\Models\RiderLedgerEntry::where('user_id', $rider->id)->value('note'));
    }

    public function test_buyer_says_when_they_are_usually_home(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $this->postJson('/api/addresses', ['name' => 'Bea', 'line1' => '7 Pine St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'available_times' => 'Mon–Fri after 6 pm'])->assertCreated();
        $this->assertSame('Mon–Fri after 6 pm', $buyer->addresses()->value('available_times'));
        $this->assertSame('Mon–Fri after 6 pm', $buyer->addresses()->first()->toArray()['available_times']); // copied into the order at checkout
    }

    public function test_buyer_sees_how_far_the_rider_is_while_out_for_delivery(): void
    {
        $rider = User::factory()->create(['name' => 'Ravi Kumar']);
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true, 'rider_last_lat' => 30.70, 'rider_last_lng' => 76.71, 'rider_last_located_at' => now()->subMinutes(2)])->save();
        $order = $this->order(['status' => 'out_for_delivery', 'payment_status' => 'paid', 'delivery_method' => 'own_rider', 'delivery_partner_id' => $rider->id,
            'delivery_address' => ['line1' => 'x', 'latitude' => 30.72, 'longitude' => 76.71]]);
        Sanctum::actingAs($order->user);
        $live = $this->getJson('/api/orders')->assertOk()->json('data.0.rider_live');
        $this->assertNotNull($live);
        $this->assertEqualsWithDelta(2.2, $live['km'], 0.2);
        $this->assertStringContainsString('google.com/maps', $live['map']);

        // An old location (over 15 minutes) isn't shown.
        $rider->forceFill(['rider_last_located_at' => now()->subHour()])->save();
        $this->assertNull($this->getJson('/api/orders')->assertOk()->json('data.0.rider_live'));
    }

    public function test_unpaid_order_reminds_the_buyer_twice(): void
    {
        Notification::fake();
        $order = $this->order(['status' => 'pending_payment', 'payment_status' => 'unpaid']);
        $this->assertSame(0, \App\Support\PaymentReminders::send()); // too early
        $this->travel(2)->hours();
        $this->assertSame(1, \App\Support\PaymentReminders::send());
        $this->assertSame(0, \App\Support\PaymentReminders::send());
        $this->travel(1)->days();
        $this->assertSame(1, \App\Support\PaymentReminders::send());
        Notification::assertSentTo($order->user, \App\Notifications\PaymentReminder::class);
    }
}
