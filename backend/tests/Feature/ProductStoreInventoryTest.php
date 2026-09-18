<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductStoreInventoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Store, 1: Store} Downtown (customer is inside), Uptown (far) */
    private function twoStores(): array
    {
        return [
            Store::create([
                'name' => 'Downtown', 'line1' => '1 A St', 'city' => 'New York', 'state' => 'NY',
                'postal_code' => '10001', 'latitude' => 40.7128, 'longitude' => -74.0060,
                'delivery_radius_km' => 6, 'is_active' => true,
            ]),
            Store::create([
                'name' => 'Uptown', 'line1' => '2 B St', 'city' => 'Yonkers', 'state' => 'NY',
                'postal_code' => '10701', 'latitude' => 40.9312, 'longitude' => -73.8988,
                'delivery_radius_km' => 6, 'is_active' => true,
            ]),
        ];
    }

    private function product(string $name, int $globalQty = 50): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true]),
            'name' => $name, 'slug' => Str::slug($name),
            'is_active' => true, 'inventory_quantity' => $globalQty,
        ]);
    }

    private function stock(Product $p, Store $s, int $qty, bool $stocked = true): void
    {
        StoreInventory::create([
            'store_id' => $s->id, 'product_id' => $p->id, 'product_variant_id' => null,
            'quantity' => $qty, 'is_stocked' => $stocked,
        ]);
    }

    private const DOWNTOWN = ['lat' => 40.7130, 'lng' => -74.0055];

    private const UPTOWN = ['lat' => 40.9310, 'lng' => -73.8990];

    private function near(array $c): string
    {
        return "lat={$c['lat']}&lng={$c['lng']}";
    }

    public function test_product_with_no_store_rows_uses_its_single_stock_everywhere(): void
    {
        $this->twoStores();
        $this->product('Everywhere', globalQty: 12);

        $near = $this->getJson('/api/products?'.$this->near(self::DOWNTOWN))->assertOk();
        $near->assertJsonFragment(['name' => 'Everywhere']);
        $near->assertJsonPath('data.0.inventory_quantity', 12);
    }

    public function test_per_store_stock_hides_a_product_the_local_store_does_not_carry(): void
    {
        [$downtown] = $this->twoStores();
        $p = $this->product('Downtown special');
        $this->stock($p, $downtown, 8); // only Downtown carries it

        $this->getJson('/api/products?'.$this->near(self::DOWNTOWN))
            ->assertOk()->assertJsonFragment(['name' => 'Downtown special']);

        $this->getJson('/api/products?'.$this->near(self::UPTOWN))
            ->assertOk()->assertJsonMissing(['name' => 'Downtown special']);
    }

    public function test_zero_quantity_is_still_listed_but_flagged_out_of_stock(): void
    {
        [$downtown] = $this->twoStores();
        $p = $this->product('Sold out here');
        $this->stock($p, $downtown, 0); // carried, but none left

        $this->getJson('/api/products?'.$this->near(self::DOWNTOWN))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Sold out here')
            ->assertJsonPath('data.0.inventory_quantity', 0)
            ->assertJsonPath('data.0.out_of_stock', true);
    }

    public function test_each_store_shows_its_own_count(): void
    {
        [$downtown, $uptown] = $this->twoStores();
        $p = $this->product('Milk');
        $this->stock($p, $downtown, 3);
        $this->stock($p, $uptown, 40);

        $this->getJson('/api/products?'.$this->near(self::DOWNTOWN))
            ->assertOk()->assertJsonPath('data.0.inventory_quantity', 3);

        $this->getJson('/api/products?'.$this->near(self::UPTOWN))
            ->assertOk()->assertJsonPath('data.0.inventory_quantity', 40);
    }

    public function test_checkout_decrements_only_the_serving_store(): void
    {
        [$downtown, $uptown] = $this->twoStores();
        $p = $this->product('Milk');
        $this->stock($p, $downtown, 3);
        $this->stock($p, $uptown, 40);

        Sanctum::actingAs(User::factory()->create(['phone' => '5551234567']));
        $this->postJson('/api/cart/items', [
            'product_id' => $p->id, 'quantity' => 2, 'lat' => self::DOWNTOWN['lat'], 'lng' => self::DOWNTOWN['lng'],
        ])->assertCreated();

        $this->postJson('/api/checkout', ['address' => [
            'name' => 'Sam', 'line1' => '9 Wall St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => self::DOWNTOWN['lat'], 'longitude' => self::DOWNTOWN['lng'],
        ]])->assertCreated();

        $this->assertSame(1, StoreInventory::where(['store_id' => $downtown->id, 'product_id' => $p->id])->value('quantity'));
        $this->assertSame(40, StoreInventory::where(['store_id' => $uptown->id, 'product_id' => $p->id])->value('quantity'));
        $this->assertSame(50, $p->fresh()->inventory_quantity); // single-stock column untouched
    }

    public function test_checkout_rejects_more_than_the_serving_store_has(): void
    {
        [$downtown] = $this->twoStores();
        $p = $this->product('Milk');
        $this->stock($p, $downtown, 1);

        Sanctum::actingAs(User::factory()->create(['phone' => '5551234567']));
        $this->postJson('/api/cart/items', [
            'product_id' => $p->id, 'quantity' => 3, 'lat' => self::DOWNTOWN['lat'], 'lng' => self::DOWNTOWN['lng'],
        ])->assertStatus(422);
    }

    public function test_checkout_blocks_an_item_the_serving_store_does_not_carry(): void
    {
        [$downtown, $uptown] = $this->twoStores();
        $p = $this->product('Downtown special');
        $this->stock($p, $downtown, 5); // Uptown has no row

        $user = User::factory()->create(['phone' => '5551234567']);
        Sanctum::actingAs($user);
        // Add while "near Downtown" so the cart accepts it...
        $this->postJson('/api/cart/items', [
            'product_id' => $p->id, 'quantity' => 1, 'lat' => self::DOWNTOWN['lat'], 'lng' => self::DOWNTOWN['lng'],
        ])->assertCreated();

        // ...then check out to an Uptown address.
        $this->postJson('/api/checkout', ['address' => [
            'name' => 'Sam', 'line1' => '9 Elm', 'city' => 'Yonkers', 'state' => 'NY',
            'postal_code' => '10701', 'latitude' => self::UPTOWN['lat'], 'longitude' => self::UPTOWN['lng'],
        ]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.cart.0', "Downtown special isn't available for delivery to your area.");
    }

    public function test_category_hidden_when_nothing_is_carried_nearby(): void
    {
        [$downtown] = $this->twoStores();
        $category = Category::factory()->create(['is_active' => true, 'slug' => 'downtown-only-cat']);
        $p = Product::factory()->create([
            'category_id' => $category->id, 'is_active' => true, 'slug' => 'dt-only', 'inventory_quantity' => 5,
        ]);
        $this->stock($p, $downtown, 5);

        $this->getJson('/api/categories?'.$this->near(self::UPTOWN))
            ->assertOk()->assertJsonMissing(['slug' => 'downtown-only-cat']);

        $this->getJson('/api/categories?'.$this->near(self::DOWNTOWN))
            ->assertOk()->assertJsonFragment(['slug' => 'downtown-only-cat']);
    }

    public function test_admin_sets_per_store_stock_through_the_product_editor(): void
    {
        [$downtown, $uptown] = $this->twoStores();
        $p = $this->product('Bread');

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/products/{$p->id}", ['store_stock' => [
            ['store_id' => $downtown->id, 'variant_sku' => null, 'is_stocked' => true, 'quantity' => 15],
            ['store_id' => $uptown->id, 'variant_sku' => null, 'is_stocked' => false, 'quantity' => 0],
        ]])->assertOk()->assertJsonCount(2, 'data.store_inventory');

        $this->assertSame(15, StoreInventory::where(['store_id' => $downtown->id, 'product_id' => $p->id])->value('quantity'));
        $this->assertFalse((bool) StoreInventory::where(['store_id' => $uptown->id, 'product_id' => $p->id])->value('is_stocked'));

        // Re-submitting with only Downtown drops the Uptown row.
        $this->patchJson("/api/admin/products/{$p->id}", ['store_stock' => [
            ['store_id' => $downtown->id, 'variant_sku' => null, 'quantity' => 9],
        ]])->assertOk()->assertJsonCount(1, 'data.store_inventory');
    }
}
