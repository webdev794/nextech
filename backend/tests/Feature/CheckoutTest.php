<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_checkout_with_server_calculated_totals(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 1000, 'inventory_quantity' => 5]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $response = $this->postJson('/api/checkout', ['address' => [
            'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn',
            'state' => 'NY', 'postal_code' => '11201',
        ]]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.subtotal_cents', 2000)
            ->assertJsonPath('data.tax_cents', 177)
            ->assertJsonPath('data.delivery_fee_cents', 299)
            ->assertJsonPath('data.handling_fee_cents', 99)
            ->assertJsonPath('data.small_cart_fee_cents', 0)
            ->assertJsonPath('data.total_cents', 2575) // 2000 + 177 + 299 + 99
            ->assertJsonPath('data.items.0.unit_price_cents', 1000);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'inventory_quantity' => 3]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_small_cart_pays_a_surcharge_below_the_soft_minimum(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 500, 'inventory_quantity' => 5]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/checkout', ['address' => $this->address()])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 500)
            ->assertJsonPath('data.delivery_fee_cents', 299)
            ->assertJsonPath('data.handling_fee_cents', 99)
            ->assertJsonPath('data.small_cart_fee_cents', 199)
            ->assertJsonPath('data.total_cents', 1141); // 500 + 44 + 299 + 99 + 199
    }

    public function test_delivery_is_free_above_the_threshold_but_handling_still_applies(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 1000, 'inventory_quantity' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 4]);
        $this->postJson('/api/checkout', ['address' => $this->address()])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 4000)
            ->assertJsonPath('data.delivery_fee_cents', 0)
            ->assertJsonPath('data.handling_fee_cents', 99)
            ->assertJsonPath('data.small_cart_fee_cents', 0)
            ->assertJsonPath('data.total_cents', 4454); // 4000 + 355 + 0 + 99
    }

    public function test_checkout_stores_delivery_instructions(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['inventory_quantity' => 3]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/checkout', [
            'address' => $this->address(),
            'delivery_instructions' => 'Leave at the gate, call on arrival.',
        ])->assertCreated()
            ->assertJsonPath('data.delivery_instructions', 'Leave at the gate, call on arrival.');

        $this->assertDatabaseHas('orders', ['delivery_instructions' => 'Leave at the gate, call on arrival.']);
    }

    public function test_checkout_rejects_insufficient_inventory(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['inventory_quantity' => 1]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $product->update(['inventory_quantity' => 0]);

        $this->postJson('/api/checkout', ['address' => [
            'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn',
            'state' => 'NY', 'postal_code' => '11201',
        ]])->assertUnprocessable()->assertJsonValidationErrors(['cart']);
    }

    public function test_customer_can_checkout_using_saved_address(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['inventory_quantity' => 2]);
        $address = Address::create(['user_id' => $user->id, 'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201']);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/checkout', ['address_id' => $address->id])->assertCreated()->assertJsonPath('data.delivery_address.id', $address->id);
    }

    public function test_customer_cannot_create_payment_intent_for_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = \App\Models\Order::create([
            'user_id' => $owner->id,
            'subtotal_cents' => 1000,
            'tax_cents' => 89,
            'delivery_fee_cents' => 599,
            'total_cents' => 1688,
            'delivery_address' => ['name' => 'Owner', 'line1' => '10 Main Street', 'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201'],
        ]);
        Sanctum::actingAs($otherUser);

        $this->postJson("/api/orders/{$order->id}/payment-intent")
            ->assertNotFound();
    }

    public function test_compare_at_price_is_shown_but_checkout_bills_the_selling_price(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 800, 'compare_at_price_cents' => 1000, 'inventory_quantity' => 5]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.price_cents', 800)
            ->assertJsonPath('data.0.compare_at_price_cents', 1000);

        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->postJson('/api/checkout', ['address' => $this->address()])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 1600) // 2 x 800, not the compare-at
            ->assertJsonPath('data.items.0.unit_price_cents', 800)
            // The regular price is frozen onto the line for the bill/receipt.
            ->assertJsonPath('data.items.0.compare_at_price_cents', 1000);
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::factory()->create();
        return Product::factory()->create(['category_id' => $category->id, ...$attributes]);
    }

    /** @return array<string, string> */
    private function address(): array
    {
        return [
            'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn',
            'state' => 'NY', 'postal_code' => '11201',
        ];
    }
}