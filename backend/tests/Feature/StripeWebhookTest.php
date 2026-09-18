<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    private const ENDPOINT = '/api/payments/stripe/webhook';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    public function test_successful_payment_confirms_the_order(): void
    {
        $order = $this->order('pi_success', 'pending', 'pending_payment');

        $this->send('evt_1', 'payment_intent.succeeded', 'pi_success')->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'paid', 'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('stripe_events', ['id' => 'evt_1']);
    }

    public function test_duplicate_event_is_processed_only_once(): void
    {
        $order = $this->order('pi_dupe', 'pending', 'pending_payment');

        $this->send('evt_dupe', 'payment_intent.succeeded', 'pi_dupe')->assertOk();
        // Rewind the order; a second delivery of the same event id must not touch it.
        Order::whereKey($order->id)->update(['payment_status' => 'pending', 'status' => 'pending_payment']);
        $this->send('evt_dupe', 'payment_intent.succeeded', 'pi_dupe')->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'pending', 'status' => 'pending_payment',
        ]);
        $this->assertDatabaseCount('stripe_events', 1);
    }

    public function test_late_failure_after_success_does_not_downgrade_the_order(): void
    {
        $order = $this->order('pi_ooo', 'paid', 'confirmed');

        $this->send('evt_late_fail', 'payment_intent.payment_failed', 'pi_ooo')->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'paid', 'status' => 'confirmed',
        ]);
    }

    public function test_failed_payment_marks_a_pending_order_failed(): void
    {
        $order = $this->order('pi_fail', 'pending', 'pending_payment');

        $this->send('evt_fail', 'payment_intent.payment_failed', 'pi_fail')->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'failed', 'status' => 'pending_payment',
        ]);
    }

    public function test_canceled_event_cancels_a_pending_order(): void
    {
        $order = $this->order('pi_cancel', 'pending', 'pending_payment');

        $this->send('evt_cancel', 'payment_intent.canceled', 'pi_cancel')->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'cancelled', 'status' => 'cancelled',
        ]);
    }

    public function test_late_success_does_not_resurrect_an_admin_cancelled_order(): void
    {
        $order = $this->order('pi_stale', 'cancelled', 'cancelled');

        $this->send('evt_stale', 'payment_intent.succeeded', 'pi_stale')->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'cancelled', 'status' => 'cancelled',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_x', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_x']]]);

        $this->call('POST', self::ENDPOINT, server: [
            'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload)->assertStatus(400);

        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_missing_webhook_secret_returns_503(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->send('evt_no_secret', 'payment_intent.succeeded', 'pi_none')->assertStatus(503);
    }

    private function order(string $intentId, string $paymentStatus, string $status): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'subtotal_cents' => 1000,
            'tax_cents' => 89,
            'delivery_fee_cents' => 599,
            'total_cents' => 1688,
            'delivery_address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street',
                'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201',
            ],
            'stripe_payment_intent_id' => $intentId,
        ]);
    }

    private function send(string $eventId, string $type, string $intentId): TestResponse
    {
        $payload = json_encode([
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => ['id' => $intentId]],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);

        return $this->call('POST', self::ENDPOINT, server: [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);
    }
}
