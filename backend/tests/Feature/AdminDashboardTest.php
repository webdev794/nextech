<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_summarise_orders_and_paid_revenue(): void
    {
        $discounted = $this->order('confirmed', 'paid', 2000);
        $this->line($discounted, quantity: 2, unit: 800, regular: 1000); // $4.00 discount
        $this->order('packing', 'paid', 3000, attributes: ['payment_method' => 'cod']);
        $this->order('pending_payment', 'pending', 1500);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/metrics')
            ->assertOk()
            ->assertJsonPath('data.orders_total', 3)
            ->assertJsonPath('data.awaiting_fulfilment', 2)
            ->assertJsonPath('data.revenue_cents', 5000)
            ->assertJsonPath('data.avg_order_cents', 2500) // 5000 paid / 2 paid orders
            ->assertJsonPath('data.discount_cents', 400)
            ->assertJsonPath('data.cod_orders', 1)
            ->assertJsonPath('data.refunded_cents', 0)
            ->assertJsonPath('data.orders_by_status.confirmed', 1);
    }

    public function test_refunded_cents_and_gross_collected_include_store_credit(): void
    {
        // A partially-refunded order: $26.58 was collected, $20.76 given back
        // as a Stripe refund — $5.82 stays kept.
        $refundedOrder = $this->order('completed', 'partially_refunded', 2658, attributes: ['refunded_amount_cents' => 2076]);
        // A separate order gets $5.00 of store credit for a complaint — money
        // given back just as surely as a Stripe refund, even though this
        // order's own payment_status and total_cents are untouched.
        $creditedOrder = $this->order('completed', 'paid', 1000);
        GiftCard::create([
            'code' => 'GC-DASH-0001', 'pin_hash' => 'x', 'user_id' => $creditedOrder->user_id,
            'order_id' => $creditedOrder->id, 'initial_cents' => 500, 'balance_cents' => 500,
        ]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/metrics')
            ->assertOk()
            // Refunded = $20.76 Stripe refund + $5.00 store credit.
            ->assertJsonPath('data.refunded_cents', 2576)
            // Gross collected = $26.58 (partially refunded) + $10.00 (paid) — the
            // whole pie the Payments-vs-refunds chart is split from.
            ->assertJsonPath('data.gross_collected_cents', 3658);
    }

    public function test_insights_return_a_weekday_hour_activity_grid(): void
    {
        Carbon::setTestNow('2026-09-15 14:00:00');

        $this->order('confirmed', 'paid', 2000);
        $this->order('packing', 'paid', 3000);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/metrics/insights')
            ->assertOk()
            ->assertJsonCount(7, 'data.activity.matrix')
            ->assertJsonCount(24, 'data.activity.matrix.0')
            ->assertJsonPath('data.activity.rows.0', 'Mon')
            ->assertJsonPath('data.activity.peak', 2); // both orders land on Mon 14:00

        Carbon::setTestNow();
    }

    public function test_insights_bucket_by_the_requested_timezone_not_the_servers(): void
    {
        // 2026-09-15 20:00 UTC (Tuesday) is 2026-09-16 01:30 in Asia/Kolkata
        // (+5:30) — Wednesday, 1am. The chart should follow whichever the
        // admin's browser reports, not the server's UTC clock.
        Carbon::setTestNow('2026-09-15 20:00:00');
        $this->order('confirmed', 'paid', 2000);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/metrics/insights')
            ->assertOk()
            ->assertJsonPath('data.activity.rows.1', 'Tue')
            ->assertJsonPath('data.activity.matrix.1.20', 1); // Tue 20:00 in UTC

        $this->getJson('/api/admin/metrics/insights?tz=Asia/Kolkata')
            ->assertOk()
            ->assertJsonPath('data.activity.matrix.2.1', 1); // Wed 01:00 in IST

        Carbon::setTestNow();
    }

    public function test_timeseries_and_compare_bucket_by_the_requested_timezone(): void
    {
        // 2026-09-15 20:00 UTC (Tue) is 2026-09-16 (Wed) in Asia/Kolkata —
        // a different calendar day, so it must land in a different bucket.
        Carbon::setTestNow('2026-09-15 22:00:00');
        $this->order('confirmed', 'paid', 2000);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/metrics/timeseries?bucket=day')
            ->assertOk()
            ->assertJsonPath('data.series.13.period', '2026-09-15')
            ->assertJsonPath('data.series.13.orders', 1);

        $this->getJson('/api/admin/metrics/timeseries?bucket=day&tz=Asia/Kolkata')
            ->assertOk()
            ->assertJsonPath('data.series.13.period', '2026-09-16')
            ->assertJsonPath('data.series.13.orders', 1);

        Carbon::setTestNow();
    }

    public function test_non_admin_cannot_view_insights(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/metrics/insights')->assertForbidden();
    }

    public function test_customer_list_excludes_admins_and_totals_paid_spend(): void
    {
        $this->admin();
        $spender = User::factory()->create();
        User::factory()->create(); // a second plain customer
        $this->order('confirmed', 'paid', 2500, $spender);
        $this->order('pending_payment', 'pending', 900, $spender);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/customers')->assertOk();
        $response->assertJsonPath('meta.total', 2); // two plain customers; both admins excluded
        $row = collect($response->json('data'))->firstWhere('id', $spender->id);
        $this->assertSame(2, $row['orders_count']);
        $this->assertSame(2500, $row['spent_cents']);
    }

    public function test_notifications_surface_overdue_cash_and_recent_bad_feedback(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $riderToday = User::factory()->create(['is_rider' => true, 'name' => 'Today Rider']);
        $order1 = $this->order('completed', 'paid', 1000, attributes: [
            'payment_method' => 'cod', 'delivery_partner_id' => $riderToday->id, 'cash_collected_at' => now(),
        ]);

        $riderOverdue = User::factory()->create(['is_rider' => true, 'name' => 'Overdue Rider']);
        $order2 = $this->order('completed', 'paid', 2000, attributes: [
            'payment_method' => 'cod', 'delivery_partner_id' => $riderOverdue->id, 'cash_collected_at' => now()->subDay(),
        ]);

        RiderReview::create(['order_id' => $order2->id, 'rider_id' => $riderOverdue->id, 'user_id' => $order2->user_id, 'rating' => 1, 'comment' => 'Late and rude', 'source' => 'delivery']);

        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/admin/notifications')->assertOk();

        $res->assertJsonCount(1, 'data.cash_overdue');
        $res->assertJsonPath('data.cash_overdue.0.rider_name', 'Overdue Rider');
        $res->assertJsonPath('data.cash_overdue.0.holding_cents', 2000);

        $res->assertJsonCount(1, 'data.negative_feedback');
        $res->assertJsonPath('data.negative_feedback.0.order_id', $order2->id);
        $res->assertJsonPath('data.negative_feedback.0.rating', 1);

        // recent_ratings is the "good news too" feed for the toast — it
        // includes the 5-star delivery alongside the 1-star one.
        RiderReview::create(['order_id' => $order1->id, 'rider_id' => $riderToday->id, 'user_id' => $order1->user_id, 'rating' => 5, 'comment' => null, 'source' => 'delivery']);
        $res = $this->getJson('/api/admin/notifications')->assertOk();
        $res->assertJsonCount(2, 'data.recent_ratings');
        $ratings = collect($res->json('data.recent_ratings'))->pluck('rating')->sort()->values()->all();
        $this->assertSame([1, 5], $ratings);

        Carbon::setTestNow();
    }

    public function test_notifications_surface_orders_awaiting_packing_and_refused_cod(): void
    {
        $toPack = $this->order('confirmed', 'paid', 1200);
        $this->order('packing', 'paid', 900); // already being packed — not "awaiting"
        $refused = $this->order('cancelled', 'cancelled', 500, attributes: [
            'cancelled_by' => 'rider', 'cancel_reason' => 'Customer refused to pay',
        ]);
        $this->order('cancelled', 'cancelled', 500, attributes: [
            'cancelled_by' => 'rider', 'cancel_reason' => 'Already returned', 'items_returned_at' => now(),
        ]);

        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/admin/notifications')->assertOk();

        $res->assertJsonCount(1, 'data.awaiting_packing');
        $res->assertJsonPath('data.awaiting_packing.0.order_id', $toPack->id);

        $res->assertJsonCount(1, 'data.refused_cod');
        $res->assertJsonPath('data.refused_cod.0.order_id', $refused->id);
        $res->assertJsonPath('data.refused_cod.0.reason', 'Customer refused to pay');
    }

    public function test_cash_overdue_is_ordered_longest_overdue_first(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');

        $riderTwoDays = User::factory()->create(['is_rider' => true, 'name' => 'Two Days']);
        $this->order('completed', 'paid', 1000, attributes: [
            'payment_method' => 'cod', 'delivery_partner_id' => $riderTwoDays->id, 'cash_collected_at' => now()->subDays(2),
        ]);
        $riderOneDay = User::factory()->create(['is_rider' => true, 'name' => 'One Day']);
        $this->order('completed', 'paid', 1000, attributes: [
            'payment_method' => 'cod', 'delivery_partner_id' => $riderOneDay->id, 'cash_collected_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/notifications')->assertOk()
            ->assertJsonPath('data.cash_overdue.0.rider_name', 'Two Days')
            ->assertJsonPath('data.cash_overdue.1.rider_name', 'One Day');

        Carbon::setTestNow();
    }

    public function test_notifications_surface_recent_gift_cards_and_refunds(): void
    {
        $order = $this->order('completed', 'paid', 5000);
        GiftCard::create([
            'code' => 'GC-NOTF-0001', 'pin_hash' => 'x', 'user_id' => $order->user_id,
            'order_id' => $order->id, 'initial_cents' => 1000, 'balance_cents' => 1000, 'reason' => 'Missing item',
        ]);
        $order->refunds()->create(['amount_cents' => 500, 'reason' => 'Late delivery', 'created_by' => $this->admin()->id]);

        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/admin/notifications')->assertOk();

        $res->assertJsonCount(2, 'data.financial_activity');
        $types = collect($res->json('data.financial_activity'))->pluck('type')->sort()->values()->all();
        $this->assertSame(['gift_card', 'refund'], $types);
        $res->assertJsonPath('data.financial_activity_total', 2);
    }

    public function test_notification_totals_stay_accurate_when_the_sample_is_capped(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 13; $i++) {
            $order = $this->order('completed', 'paid', 1000);
            $order->refunds()->create(['amount_cents' => 100, 'reason' => "Refund {$i}", 'created_by' => $admin->id]);
        }

        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/admin/notifications')->assertOk();

        // The sample is capped at 10 for display, but the reported total
        // reflects all 13 so "+N more" in the bell isn't a lie.
        $res->assertJsonCount(10, 'data.financial_activity');
        $res->assertJsonPath('data.financial_activity_total', 13);
    }

    public function test_non_admin_cannot_view_metrics(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/metrics')->assertForbidden();
    }

    public function test_customer_detail_returns_that_customers_orders(): void
    {
        $customer = User::factory()->create();
        $this->order('confirmed', 'paid', 2500, $customer);
        $this->order('pending_payment', 'pending', 900, User::factory()->create());

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email)
            ->assertJsonCount(1, 'data.orders');
    }

    public function test_customer_detail_hides_admin_accounts(): void
    {
        $otherAdmin = $this->admin();
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/customers/{$otherAdmin->id}")->assertNotFound();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(string $status, string $paymentStatus, int $total, ?User $user = null, array $attributes = []): Order
    {
        return Order::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'subtotal_cents' => $total,
            'tax_cents' => 0,
            'delivery_fee_cents' => 0,
            'total_cents' => $total,
            'delivery_address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street',
                'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201',
            ],
            ...$attributes,
        ]);
    }

    private function line(Order $order, int $quantity, int $unit, ?int $regular = null): void
    {
        $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Line item',
            'sku' => 'GDP-TEST-'.$order->items()->count(),
            'quantity' => $quantity,
            'unit_price_cents' => $unit,
            'compare_at_price_cents' => $regular,
            'line_total_cents' => $unit * $quantity,
        ]);
    }
}
