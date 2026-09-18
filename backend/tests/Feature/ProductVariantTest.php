<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_active_variants_and_a_price_range(): void
    {
        $product = $this->product(['price_cents' => 349]);
        $product->variants()->createMany([
            ['label' => '500 g', 'sku' => 'V-500', 'price_cents' => 300, 'inventory_quantity' => 5, 'sort_order' => 1],
            ['label' => '1 kg', 'sku' => 'V-1000', 'price_cents' => 550, 'inventory_quantity' => 5, 'sort_order' => 2],
            ['label' => 'hidden', 'sku' => 'V-X', 'price_cents' => 100, 'inventory_quantity' => 0, 'is_active' => false],
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.price_min_cents', 300)
            ->assertJsonPath('data.0.price_max_cents', 550)
            ->assertJsonCount(2, 'data.0.variants');
    }

    public function test_variant_product_can_be_added_as_the_base_option(): void
    {
        $product = $this->product(['price_cents' => 349, 'inventory_quantity' => 10]);
        $product->variants()->create(['label' => '1 kg', 'sku' => 'V1', 'price_cents' => 500, 'inventory_quantity' => 5]);
        Sanctum::actingAs(User::factory()->create());

        // No product_variant_id -> the plain product option, at its own price.
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 698)
            ->assertJsonPath('data.items.0.product_variant', null);
    }

    public function test_adding_a_variant_uses_its_price_and_stock(): void
    {
        $product = $this->product(['price_cents' => 999]);
        $variant = $product->variants()->create(['label' => '1 kg', 'sku' => 'V1', 'price_cents' => 640, 'inventory_quantity' => 3]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 1280)
            ->assertJsonPath('data.items.0.product_variant.label', '1 kg');

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_a_variant_from_another_product_or_inactive_is_rejected(): void
    {
        $product = $this->product();
        $product->variants()->create(['label' => '1 kg', 'sku' => 'V1', 'price_cents' => 500, 'inventory_quantity' => 5]);
        $other = $this->product();
        $otherVariant = $other->variants()->create(['label' => 'big', 'sku' => 'V2', 'price_cents' => 700, 'inventory_quantity' => 5]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'product_variant_id' => $otherVariant->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_a_simple_product_still_adds_without_a_variant(): void
    {
        $product = $this->product(['price_cents' => 250, 'inventory_quantity' => 10]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 500);
    }

    public function test_checkout_snapshots_the_variant_and_decrements_variant_stock(): void
    {
        $product = $this->product(['price_cents' => 999, 'inventory_quantity' => 99]);
        $variant = $product->variants()->create(['label' => '2 kg', 'sku' => 'V2KG', 'price_cents' => 1200, 'inventory_quantity' => 4]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 3]);

        $this->postJson('/api/checkout', ['address' => [
            'name' => 'T', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001',
        ]])->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 3600)
            ->assertJsonPath('data.items.0.variant_label', '2 kg')
            ->assertJsonPath('data.items.0.unit_price_cents', 1200);

        $this->assertSame(1, $variant->fresh()->inventory_quantity);
        $this->assertSame(99, $product->fresh()->inventory_quantity);
        $this->assertDatabaseHas('order_items', ['product_variant_id' => $variant->id, 'sku' => 'V2KG']);
    }

    public function test_admin_can_create_a_product_with_variants(): void
    {
        $category = Category::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name' => 'Basmati Rice',
            'sku' => 'RICE-BASE',
            'price_cents' => 0,
            'variants' => [
                ['label' => '1 kg', 'sku' => 'RICE-1KG', 'price_cents' => 400, 'inventory_quantity' => 20],
                ['label' => '5 kg', 'sku' => 'RICE-5KG', 'price_cents' => 1800, 'inventory_quantity' => 8],
            ],
        ])->assertCreated()
            ->assertJsonCount(2, 'data.variants');

        $this->assertDatabaseHas('product_variants', ['sku' => 'RICE-5KG', 'price_cents' => 1800]);
    }

    public function test_admin_can_add_edit_and_remove_variant_rows(): void
    {
        $product = $this->product();
        $keep = $product->variants()->create(['label' => '1 kg', 'sku' => 'K1', 'price_cents' => 500, 'inventory_quantity' => 5]);
        $drop = $product->variants()->create(['label' => 'old', 'sku' => 'D1', 'price_cents' => 900, 'inventory_quantity' => 1]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/products/{$product->id}", [
            'variants' => [
                ['id' => $keep->id, 'label' => '1 kg', 'sku' => 'K1', 'price_cents' => 525, 'inventory_quantity' => 7],
                ['id' => $drop->id, '_delete' => true, 'label' => 'old', 'sku' => 'D1', 'price_cents' => 900],
                ['label' => '2 kg', 'sku' => 'K2', 'price_cents' => 980, 'inventory_quantity' => 3],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('product_variants', ['id' => $keep->id, 'price_cents' => 525]);
        $this->assertDatabaseMissing('product_variants', ['id' => $drop->id]);
        $this->assertDatabaseHas('product_variants', ['sku' => 'K2', 'product_id' => $product->id]);
    }

    public function test_removing_a_variant_that_is_on_an_order_is_blocked(): void
    {
        $product = $this->product();
        $variant = $product->variants()->create(['label' => '1 kg', 'sku' => 'ON-ORD', 'price_cents' => 500, 'inventory_quantity' => 5]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'payment_status' => 'paid',
            'subtotal_cents' => 500, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 500,
            'delivery_address' => ['name' => 'X', 'line1' => '1', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'product_name' => $product->name, 'sku' => 'ON-ORD', 'variant_label' => '1 kg',
            'quantity' => 1, 'unit_price_cents' => 500, 'line_total_cents' => 500,
        ]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/products/{$product->id}", [
            'variants' => [['id' => $variant->id, '_delete' => true, 'label' => '1 kg', 'sku' => 'ON-ORD', 'price_cents' => 500]],
        ])->assertStatus(422);

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_duplicate_variant_sku_is_rejected(): void
    {
        ProductVariant::factory()->create(['sku' => 'TAKEN']);
        $product = $this->product();
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/products/{$product->id}", [
            'variants' => [['label' => '1 kg', 'sku' => 'TAKEN', 'price_cents' => 500]],
        ])->assertStatus(422);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }
}
