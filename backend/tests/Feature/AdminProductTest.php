<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product_with_a_generated_slug(): void
    {
        $category = Category::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name' => 'Cold Pressed Olive Oil',
            'sku' => 'GDP-OIL-01',
            'price_cents' => 1299,
            'inventory_quantity' => 40,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'cold-pressed-olive-oil')
            ->assertJsonPath('data.category.name', $category->name);

        $this->assertDatabaseHas('products', ['sku' => 'GDP-OIL-01', 'price_cents' => 1299]);
    }

    public function test_admin_can_update_price_and_inventory(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/products/{$product->id}", ['price_cents' => 555, 'inventory_quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.price_cents', 555);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'inventory_quantity' => 3]);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'sku' => 'DUPE-1']);
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/products', [
            'category_id' => $category->id, 'name' => 'Another', 'sku' => 'DUPE-1', 'price_cents' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors(['sku']);
    }

    public function test_product_in_an_order_cannot_be_deleted(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed', 'payment_status' => 'paid',
            'subtotal_cents' => 100, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 100,
            'delivery_address' => ['name' => 'X', 'line1' => 'Y', 'city' => 'Z', 'state' => 'NY', 'postal_code' => '11201'],
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
            'quantity' => 1, 'unit_price_cents' => 100, 'line_total_cents' => 100,
        ]);
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/products/{$product->id}")->assertStatus(409);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_unused_product_can_be_deleted(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_non_admin_cannot_manage_products(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/products')->assertForbidden();
        $this->patchJson("/api/admin/products/{$product->id}", ['price_cents' => 1])->assertForbidden();
    }

    public function test_product_list_sorts_newest_first_by_default_and_by_other_keys(): void
    {
        $category = Category::factory()->create();
        $old = Product::factory()->create(['category_id' => $category->id, 'name' => 'Zebra bar', 'inventory_quantity' => 2, 'created_at' => now()->subDays(3)]);
        $new = Product::factory()->create(['category_id' => $category->id, 'name' => 'Apple juice', 'inventory_quantity' => 9, 'created_at' => now()]);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/products')
            ->assertOk()->assertJsonPath('data.0.id', $new->id);

        $this->getJson('/api/admin/products?sort=name')
            ->assertOk()->assertJsonPath('data.0.id', $new->id); // "Apple" before "Zebra"

        $this->getJson('/api/admin/products?sort=stock_low')
            ->assertOk()->assertJsonPath('data.0.id', $old->id); // 2 < 9

        $this->getJson('/api/admin/products?sort=stock_high')
            ->assertOk()->assertJsonPath('data.0.id', $new->id);
    }

    public function test_product_list_can_be_filtered_and_sorted_by_store(): void
    {
        $category = Category::factory()->create();
        $store = Store::create([
            'name' => 'Hub', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001',
            'latitude' => 40.7, 'longitude' => -74.0, 'delivery_radius_km' => 5, 'is_active' => true,
        ]);
        $everywhere = Product::factory()->create(['category_id' => $category->id, 'inventory_quantity' => 100]);
        $atStore = Product::factory()->create(['category_id' => $category->id, 'inventory_quantity' => 0]);
        StoreInventory::create(['store_id' => $store->id, 'product_id' => $atStore->id, 'product_variant_id' => null, 'quantity' => 4, 'is_stocked' => true]);
        $notHere = Product::factory()->create(['category_id' => $category->id, 'inventory_quantity' => 50]);
        StoreInventory::create(['store_id' => $store->id, 'product_id' => $notHere->id, 'product_variant_id' => null, 'quantity' => 0, 'is_stocked' => false]);

        Sanctum::actingAs($this->admin());

        $res = $this->getJson("/api/admin/products?store_id={$store->id}&sort=stock_low")->assertOk();
        $ids = array_column($res->json('data'), 'id');

        $this->assertContains($everywhere->id, $ids);  // no per-store row → sold everywhere
        $this->assertContains($atStore->id, $ids);
        $this->assertNotContains($notHere->id, $ids);   // explicitly not stocked here
        $this->assertSame($atStore->id, $ids[0]);        // effective stock 4 < 100
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }
}
