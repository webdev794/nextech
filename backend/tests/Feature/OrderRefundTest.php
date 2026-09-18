<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test_x']);
    }

    public function test_an_unpaid_or_fully_refunded_order_cannot_be_refunded(): void
    {
        Sanctum::actingAs($this->admin());

        $unpaid = $this->order(['status' => 'confirmed', 'payment_status' => 'pending', 'stripe_payment_intent_id' => 'pi_1']);
        $this->postJson("/api/admin/orders/{$unpaid->id}/refund")->assertStatus(422);

        $done = $this->order(['status' => 'cancelled', 'payment_status' => 'refunded', 'stripe_payment_intent_id' => 'pi_2', 'refunded_amount_cents' => 1000]);
        $this->postJson("/api/admin/orders/{$done->id}/refund")->assertStatus(422);
    }

    public function test_over_refunding_the_remaining_balance_is_rejected(): void
    {
        $order = $this->order(['status' => 'completed', 'payment_status' => 'partially_refunded', 'stripe_payment_intent_id' => 'pi_1', 'refunded_amount_cents' => 600]);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$order->id}/refund", ['amount_cents' => 500])
            ->assertStatus(422); // only 400 remaining
    }

    public function test_a_cod_order_without_a_payment_intent_is_rejected(): void
    {
        $order = $this->order(['status' => 'cancelled', 'payment_status' => 'refund_pending', 'payment_method' => 'cod']);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$order->id}/refund")
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'no Stripe payment'));
    }

    public function test_refund_needs_stripe_to_be_configured(): void
    {
        config(['services.stripe.secret' => null]);
        $order = $this->order(['status' => 'cancelled', 'payment_status' => 'refund_pending', 'stripe_payment_intent_id' => 'pi_1']);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$order->id}/refund")->assertStatus(503);
    }

    public function test_admin_order_list_exposes_a_stripe_dashboard_link(): void
    {
        config(['services.stripe.secret' => 'sk_test_abc']);
        $order = $this->order(['status' => 'cancelled', 'payment_status' => 'refunded', 'stripe_payment_intent_id' => 'pi_123']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.stripe_dashboard_url', 'https://dashboard.stripe.com/test/payments/pi_123');
    }

    public function test_an_order_without_a_payment_intent_has_no_dashboard_link(): void
    {
        $order = $this->order(['status' => 'confirmed', 'payment_status' => 'pending', 'payment_method' => 'cod']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/orders')->assertOk()->assertJsonPath('data.0.stripe_dashboard_url', null);
    }

    public function test_a_non_admin_cannot_refund(): void
    {
        $order = $this->order(['status' => 'cancelled', 'payment_status' => 'refund_pending', 'stripe_payment_intent_id' => 'pi_1']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/admin/orders/{$order->id}/refund")->assertForbidden();
    }

    public function test_the_order_view_shows_the_refund_reason_and_who_issued_it(): void
    {
        $agent = $this->admin();
        $agent->update(['name' => 'Support Agent']);
        $order = $this->order(['status' => 'completed', 'payment_status' => 'partially_refunded', 'stripe_payment_intent_id' => 'pi_1', 'refunded_amount_cents' => 500]);
        $order->refunds()->create(['amount_cents' => 500, 'reason' => 'Late delivery', 'created_by' => $agent->id, 'stripe_refund_id' => 're_1']);

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.refunds.0.reason', 'Late delivery')
            ->assertJsonPath('data.refunds.0.creator.name', 'Support Agent');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(array $overrides): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            ...$overrides,
        ]);
    }
}
