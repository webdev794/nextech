<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lists_only_their_own_orders(): void
    {
        $user = User::factory()->create();
        $this->order($user);
        $this->order($user);
        $this->order(User::factory()->create());

        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_customer_can_view_their_order_with_items(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => \App\Models\Product::factory()->create([
                'category_id' => \App\Models\Category::factory()->create()->id,
            ])->id,
            'product_name' => 'Organic Bananas',
            'sku' => 'GDP-PROD-001',
            'quantity' => 2,
            'unit_price_cents' => 299,
            'line_total_cents' => 598,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.items.0.product_name', 'Organic Bananas')
            ->assertJsonPath('data.items.0.line_total_cents', 598);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $order = $this->order(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$order->id}")->assertNotFound();
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    private function order(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'subtotal_cents' => 1000,
            'tax_cents' => 89,
            'delivery_fee_cents' => 599,
            'total_cents' => 1688,
            'delivery_address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street',
                'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201',
            ],
        ]);
    }
}
