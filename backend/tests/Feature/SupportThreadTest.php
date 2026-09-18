<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_opens_a_thread_for_their_own_order(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/support/threads', [
            'order_id' => $order->id,
            'issue_type' => 'item_missing',
            'message' => 'The eggs were not in the bag.',
        ])->assertCreated()
            ->assertJsonPath('data.issue_type', 'item_missing')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.messages.0.body', fn ($b) => str_contains($b, 'Support request opened'))
            ->assertJsonPath('data.messages.1.body', 'The eggs were not in the bag.');
    }

    public function test_customer_opens_a_general_thread_without_an_order(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/support/threads', ['issue_type' => 'other', 'message' => 'Question about delivery hours.'])
            ->assertCreated()
            ->assertJsonPath('data.order_id', null)
            ->assertJsonPath('data.messages.0.body', 'Support request opened — other.');
    }

    public function test_customer_cannot_open_a_thread_against_another_users_order(): void
    {
        $order = $this->order(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/support/threads', [
            'order_id' => $order->id, 'issue_type' => 'other', 'message' => 'hi',
        ])->assertNotFound();
    }

    public function test_an_unknown_issue_type_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/support/threads', ['issue_type' => 'aliens', 'message' => 'hi'])
            ->assertStatus(422)->assertJsonValidationErrors(['issue_type']);
    }

    public function test_customer_cannot_read_another_users_thread(): void
    {
        $thread = SupportThread::create(['user_id' => User::factory()->create()->id, 'issue_type' => 'other', 'status' => 'open']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/support/threads/{$thread->id}")->assertNotFound();
        $this->postJson("/api/support/threads/{$thread->id}/messages", ['body' => 'x'])->assertNotFound();
    }

    public function test_a_customer_reply_reopens_a_resolved_thread(): void
    {
        $user = User::factory()->create();
        $thread = SupportThread::create(['user_id' => $user->id, 'issue_type' => 'other', 'status' => 'resolved', 'resolved_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson("/api/support/threads/{$thread->id}/messages", ['body' => 'still not fixed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_admin_lists_filters_replies_and_resolves(): void
    {
        $user = User::factory()->create();
        $thread = SupportThread::create(['user_id' => $user->id, 'issue_type' => 'item_damaged', 'status' => 'open', 'last_message_at' => now()]);
        SupportThread::create(['user_id' => $user->id, 'issue_type' => 'other', 'status' => 'resolved', 'last_message_at' => now()->subDay()]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson('/api/admin/support/threads?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.open', 1);

        $this->getJson('/api/admin/support/threads?status=open')->assertJsonPath('data.0.needs_reply', true);

        $this->postJson("/api/admin/support/threads/{$thread->id}/messages", ['body' => 'Looking into it'])
            ->assertOk()
            ->assertJsonPath('data.messages.0.is_staff', true)
            ->assertJsonPath('data.needs_reply', false);

        $this->patchJson("/api/admin/support/threads/{$thread->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_admin_can_attach_an_order_to_a_thread_opened_without_one(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $thread = SupportThread::create(['user_id' => $user->id, 'issue_type' => 'other', 'status' => 'open']);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson("/api/admin/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.order', null)
            ->assertJsonPath('data.user.orders.0.id', $order->id);

        $this->patchJson("/api/admin/support/threads/{$thread->id}", ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order.id', $order->id);
    }

    public function test_admin_cannot_attach_another_customers_order_to_a_thread(): void
    {
        $user = User::factory()->create();
        $otherOrder = $this->order(User::factory()->create());
        $thread = SupportThread::create(['user_id' => $user->id, 'issue_type' => 'other', 'status' => 'open']);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/support/threads/{$thread->id}", ['order_id' => $otherOrder->id])
            ->assertStatus(422);
    }

    public function test_an_internal_note_is_hidden_from_the_customer_but_visible_to_admin(): void
    {
        $user = User::factory()->create();
        $thread = SupportThread::create(['user_id' => $user->id, 'issue_type' => 'other', 'status' => 'open']);
        $thread->post($user, 'Where is my refund?');
        $thread->post(null, 'Refund reason: customer changed their mind', isStaff: true, system: true, internal: true);

        Sanctum::actingAs($user);
        $this->getJson("/api/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonMissing(['body' => 'Refund reason: customer changed their mind']);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson("/api/admin/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.1.internal', true);
    }

    public function test_an_internal_note_alone_does_not_mark_the_thread_as_replied_to(): void
    {
        $user = User::factory()->create();
        $thread = SupportThread::create(['user_id' => $user->id, 'issue_type' => 'other', 'status' => 'open']);
        $thread->post($user, 'Where is my refund?');
        $thread->post(null, 'Internal-only note', isStaff: true, system: true, internal: true);

        $this->assertFalse($thread->fresh()->hasStaffReply());
        $this->assertTrue($thread->fresh()->needs_reply);
    }

    public function test_a_normal_user_cannot_reach_the_admin_inbox(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/support/threads')->assertForbidden();
    }

    private function order(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id, 'status' => 'completed', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
    }
}
