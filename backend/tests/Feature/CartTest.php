<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 299, 'inventory_quantity' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal_cents', 598)
            ->assertJsonPath('data.items.0.product.id', $product->id);
    }

    public function test_adding_same_product_increments_one_cart_line(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 499, 'inventory_quantity' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.item_count', 3)
            ->assertJsonPath('data.subtotal_cents', 1497);
    }

    public function test_customer_cannot_add_more_than_available_inventory(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['inventory_quantity' => 2]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_guest_cannot_access_cart(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::factory()->create();

        return Product::factory()->create([
            'category_id' => $category->id,
            ...$attributes,
        ]);
    }
}