<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\OrderDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderDeliveredReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create([
            'name' => 'Hub', 'line1' => '1 Hub St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7128, 'longitude' => -74.0060,
            'delivery_radius_km' => 10, 'is_active' => true,
        ]);
    }

    private function rider(): User
    {
        return User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
    }

    private function order(User $customer, array $overrides = []): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'rider_accepted_at' => now(),
            'subtotal_cents' => 830, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 830,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
            ...$overrides,
        ]);

        $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Greek Yogurt', 'sku' => 'GDP-PROD-010',
            'quantity' => 2, 'unit_price_cents' => 415, 'line_total_cents' => 830,
        ]);

        return $order;
    }

    public function test_rider_completing_a_paid_delivery_emails_the_customer(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $rider = $this->rider();
        $order = $this->order($customer, ['delivery_partner_id' => $rider->id]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'Left with neighbour'])
            ->assertOk();

        Notification::assertSentTo($customer, OrderDelivered::class,
            fn (OrderDelivered $n) => $n->order->is($order));
        $this->assertNotNull($order->fresh()->receipt_emailed_at);
    }

    public function test_the_receipt_email_is_only_sent_once(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $rider = $this->rider();
        $order = $this->order($customer, ['delivery_partner_id' => $rider->id]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'x'])->assertOk();

        // A later state-changing save must not re-send.
        $order->fresh()->sendDeliveredReceiptIfReady();

        Notification::assertSentToTimes($customer, OrderDelivered::class, 1);
    }

    public function test_cash_on_delivery_needs_the_cash_before_delivery_then_emails(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $rider = $this->rider();
        $order = $this->order($customer, [
            'payment_method' => 'cod', 'payment_status' => 'pending', 'delivery_partner_id' => $rider->id,
        ]);

        Sanctum::actingAs($rider);

        // Can't complete a cash order until the cash is collected.
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'x'])->assertStatus(422);
        Notification::assertNotSentTo($customer, OrderDelivered::class);

        $this->postJson("/api/rider/orders/{$order->id}/cash-collected")->assertOk();
        // Paid but not delivered yet — still nothing sent.
        Notification::assertNotSentTo($customer, OrderDelivered::class);

        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'x'])->assertOk();

        Notification::assertSentTo($customer, OrderDelivered::class);
        $this->assertNotNull($order->fresh()->receipt_emailed_at);
    }

    public function test_admin_marking_a_paid_order_delivered_emails_the_customer(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $order = $this->order($customer);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'completed'])->assertOk();

        Notification::assertSentTo($customer, OrderDelivered::class);
    }

    public function test_the_email_carries_the_pdf_bill_and_a_summary(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, ['status' => 'completed', 'delivered_at' => now()]);

        $mail = (new OrderDelivered($order->fresh()))->toMail($customer);

        $this->assertStringContainsString('delivered', $mail->subject);
        $this->assertTrue(collect($mail->introLines)->contains(fn ($l) => str_contains($l, 'Order summary')));
        $this->assertTrue(collect($mail->introLines)->contains(fn ($l) => str_contains($l, 'Greek Yogurt')));

        $attachments = $mail->rawAttachments;
        $this->assertCount(1, $attachments);
        $this->assertSame("bill-order-{$order->id}.pdf", $attachments[0]['name']);
        $this->assertStringStartsWith('%PDF-', $attachments[0]['data']);
    }
}
