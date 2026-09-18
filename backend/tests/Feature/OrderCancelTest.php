<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_a_paid_order_before_dispatch(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['status' => 'packing', 'payment_status' => 'paid']);
        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment_status', 'refund_pending');
    }

    public function test_confirmed_and_ready_for_delivery_can_also_be_cancelled(): void
    {
        $user = User::factory()->create();
        foreach (['confirmed', 'ready_for_delivery'] as $status) {
            $order = $this->order($user, ['status' => $status, 'payment_status' => 'paid']);
            Sanctum::actingAs($user);
            $this->postJson("/api/orders/{$order->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        }
    }

    public function test_unpaid_cod_order_cancels_without_a_refund_flag(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['status' => 'confirmed', 'payment_status' => 'pending', 'payment_method' => 'cod']);
        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'cancelled');
    }

    public function test_an_order_out_for_delivery_or_completed_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (['out_for_delivery', 'completed'] as $status) {
            $order = $this->order($user, ['status' => $status, 'payment_status' => 'paid']);
            $this->postJson("/api/orders/{$order->id}/cancel")->assertStatus(422);
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_a_customer_cannot_cancel_another_customers_order(): void
    {
        $order = $this->order(User::factory()->create(), ['status' => 'confirmed', 'payment_status' => 'paid']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/orders/{$order->id}/cancel")->assertNotFound();
    }

    public function test_a_guest_cannot_cancel(): void
    {
        $order = $this->order(User::factory()->create(), ['status' => 'confirmed', 'payment_status' => 'paid']);

        $this->postJson("/api/orders/{$order->id}/cancel")->assertUnauthorized();
    }

    public function test_admin_can_mark_a_refund_pending_order_refunded(): void
    {
        $order = $this->order(User::factory()->create(), ['status' => 'cancelled', 'payment_status' => 'refund_pending']);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/orders/{$order->id}", ['refunded' => true])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'refunded');
    }

    public function test_admin_can_cancel_a_stuck_pending_payment_card_order(): void
    {
        $order = $this->order(User::factory()->create(), [
            'status' => 'pending_payment', 'payment_status' => 'pending', 'payment_method' => 'card',
        ]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment_status', 'cancelled');
    }

    public function test_a_pending_payment_order_still_cannot_jump_to_packing(): void
    {
        $order = $this->order(User::factory()->create(), [
            'status' => 'pending_payment', 'payment_status' => 'pending', 'payment_method' => 'card',
        ]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'packing'])->assertStatus(422);
    }

    public function test_customer_can_cancel_their_own_abandoned_card_order(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, [
            'status' => 'pending_payment', 'payment_status' => 'pending', 'payment_method' => 'card',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'cancelled');
    }

    private function order(User $user, array $overrides): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'subtotal_cents' => 1000, 'tax_cents' => 89, 'delivery_fee_cents' => 0, 'total_cents' => 1089,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            ...$overrides,
        ]);
    }
}
