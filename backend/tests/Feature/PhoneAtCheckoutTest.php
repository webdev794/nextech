<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhoneAtCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_is_blocked_when_the_customer_has_no_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $product = $this->product();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', ['address' => $this->address()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_checkout_accepts_a_phone_and_remembers_it_on_the_account(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $product = $this->product();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', [
            'address' => $this->address(),
            'phone' => '+1 (555) 987 6543',
        ])->assertCreated()
            ->assertJsonPath('data.delivery_address.phone', '+1 (555) 987 6543');

        $this->assertSame('+1 (555) 987 6543', $user->fresh()->phone);
    }

    public function test_checkout_uses_the_saved_phone_when_none_is_sent(): void
    {
        $user = User::factory()->create(['phone' => '+15551112222']);
        $product = $this->product();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', ['address' => $this->address()])
            ->assertCreated()
            ->assertJsonPath('data.delivery_address.phone', '+15551112222');
    }

    public function test_register_accepts_an_optional_phone(): void
    {
        config(['otp.enabled' => false]);

        $this->postJson('/api/auth/register', [
            'name' => 'Priya Shah',
            'email' => 'priya@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+919812345678',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'priya@example.com', 'phone' => '+919812345678']);
    }

    public function test_customer_can_set_a_phone_from_the_profile_endpoint(): void
    {
        $user = User::factory()->create(['phone' => null]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', ['phone' => '  +15550009999  '])
            ->assertOk()
            ->assertJsonPath('data.phone', '+15550009999');
    }

    public function test_rider_order_row_exposes_the_customer_phone(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $customer = User::factory()->create(['phone' => '+15557778888']);
        $order = \App\Models\Order::create([
            'user_id' => $customer->id,
            'status' => 'ready_for_delivery',
            'payment_status' => 'paid',
            'subtotal_cents' => 1000,
            'tax_cents' => 0,
            'delivery_fee_cents' => 0,
            'total_cents' => 1000,
            'delivery_address' => ['name' => 'Cust', 'line1' => '1 A St', 'phone' => '+15557778888'],
        ]);
        $order->items()->create([
            'product_id' => $this->product()->id,
            'product_name' => 'Milk',
            'sku' => 'X',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'line_total_cents' => 1000,
        ]);
        Sanctum::actingAs($rider);

        $this->getJson('/api/rider/orders')
            ->assertOk()
            ->assertJsonPath('data.pool.0.customer_phone', '+15557778888');
    }

    private function product(): Product
    {
        $category = Category::factory()->create();

        return Product::factory()->create(['category_id' => $category->id, 'inventory_quantity' => 5]);
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
