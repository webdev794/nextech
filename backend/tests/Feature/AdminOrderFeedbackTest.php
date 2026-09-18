<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RiderReview;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
    }

    public function test_the_orders_list_carries_the_delivery_and_chat_ratings(): void
    {
        $customer = User::factory()->create();
        $rider = User::factory()->create(['is_rider' => true]);
        $order = $this->order($customer);
        RiderReview::create([
            'order_id' => $order->id, 'rider_id' => $rider->id, 'user_id' => $customer->id,
            'rating' => 2, 'comment' => 'Missing an item', 'source' => 'delivery',
        ]);
        SupportThread::create(['user_id' => $customer->id, 'order_id' => $order->id, 'issue_type' => 'other', 'status' => 'resolved'])
            ->forceFill(['rating' => 5, 'rating_comment' => 'Sorted quickly', 'rated_at' => now()])
            ->save();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.rider_review.rating', 2)
            ->assertJsonPath('data.0.rider_review.comment', 'Missing an item')
            ->assertJsonPath('data.0.support_threads.0.rating', 5);

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.rider_review.rating', 2)
            ->assertJsonPath('data.support_threads.0.rating', 5);
    }
}
