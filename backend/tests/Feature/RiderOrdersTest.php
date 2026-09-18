<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_rider_and_a_guest_are_blocked(): void
    {
        $this->getJson('/api/rider/orders')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/rider/orders')->assertForbidden();
    }

    public function test_orders_endpoint_returns_the_pool_and_my_assigned_only(): void
    {
        $rider = $this->rider();
        $other = $this->rider();

        $mine = $this->order(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id]);
        $pool = $this->order(['status' => 'ready_for_delivery']);
        $this->order(['status' => 'ready_for_delivery', 'delivery_partner_id' => $other->id]);
        $this->order(['status' => 'completed', 'delivery_partner_id' => $rider->id]);

        Sanctum::actingAs($rider);
        $response = $this->getJson('/api/rider/orders')->assertOk();

        $response->assertJsonCount(1, 'data.assigned')
            ->assertJsonPath('data.assigned.0.id', $mine->id)
            ->assertJsonCount(1, 'data.pool')
            ->assertJsonPath('data.pool.0.id', $pool->id);
    }

    public function test_rider_claims_a_pool_order(): void
    {
        $rider = $this->rider();
        $order = $this->order(['status' => 'ready_for_delivery']);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.status', 'out_for_delivery');

        $this->assertSame($rider->id, $order->fresh()->delivery_partner_id);
        $this->assertSame($rider->name, $order->fresh()->courier_name);
    }

    public function test_cannot_claim_a_taken_or_wrong_status_order(): void
    {
        $rider = $this->rider();
        Sanctum::actingAs($rider);

        $taken = $this->order(['status' => 'ready_for_delivery', 'delivery_partner_id' => $this->rider()->id]);
        $this->postJson("/api/rider/orders/{$taken->id}/claim")->assertStatus(422);

        $notReady = $this->order(['status' => 'packing']);
        $this->postJson("/api/rider/orders/{$notReady->id}/claim")->assertStatus(422);
    }

    public function test_rider_starts_delivery_only_on_their_own_order(): void
    {
        $rider = $this->rider();
        $mine = $this->order(['status' => 'ready_for_delivery', 'delivery_partner_id' => $rider->id]);
        $notMine = $this->order(['status' => 'ready_for_delivery', 'delivery_partner_id' => $this->rider()->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$notMine->id}/status", ['status' => 'out_for_delivery'])->assertNotFound();

        $this->postJson("/api/rider/orders/{$mine->id}/status", ['status' => 'out_for_delivery'])
            ->assertOk()
            ->assertJsonPath('data.status', 'out_for_delivery');
    }

    public function test_cannot_start_delivery_before_the_store_marks_it_ready(): void
    {
        $rider = $this->rider();
        $order = $this->order(['status' => 'packing', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/status", ['status' => 'out_for_delivery'])->assertStatus(422);
    }

    public function test_cash_collected_on_a_cod_order_marks_it_paid(): void
    {
        $rider = $this->rider();
        $cod = $this->order(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id, 'payment_method' => 'cod', 'payment_status' => 'pending']);
        $card = $this->order(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id, 'payment_method' => 'card', 'payment_status' => 'paid']);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$cod->id}/cash-collected")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->postJson("/api/rider/orders/{$card->id}/cash-collected")->assertStatus(422);
    }

    public function test_rider_chats_with_the_customer_on_their_own_order(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $rider = $this->rider();
        $order = $this->order(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        // First open creates the thread + a system line.
        $this->getJson("/api/rider/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.messages.0.from', 'system');

        $this->postJson("/api/rider/orders/{$order->id}/messages", ['body' => "I'm 5 minutes away."])
            ->assertOk()
            ->assertJsonPath('data.messages.1.body', "I'm 5 minutes away.")
            ->assertJsonPath('data.messages.1.mine', true)
            ->assertJsonPath('data.messages.1.from', 'staff');

        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\RiderMessage::class);

        // The customer sees it as a normal support thread.
        Sanctum::actingAs(User::find($order->user_id));
        $threadId = SupportThread::where('order_id', $order->id)->value('id');
        $this->getJson("/api/support/threads/{$threadId}")
            ->assertOk()
            ->assertJsonFragment(['body' => "I'm 5 minutes away."]);
    }

    public function test_rider_cannot_message_an_order_that_is_not_theirs(): void
    {
        $rider = $this->rider();
        $notMine = $this->order(['status' => 'out_for_delivery', 'delivery_partner_id' => $this->rider()->id]);
        Sanctum::actingAs($rider);

        $this->getJson("/api/rider/orders/{$notMine->id}/messages")->assertNotFound();
        $this->postJson("/api/rider/orders/{$notMine->id}/messages", ['body' => 'hi'])->assertNotFound();
    }

    private function rider(): User
    {
        return User::factory()->create(['is_rider' => true]);
    }

    private function order(array $overrides): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            ...$overrides,
        ]);
    }
}
