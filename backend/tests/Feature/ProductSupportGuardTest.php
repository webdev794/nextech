<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSupportGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Product} */
    private function sellerProduct(array $productAttributes = []): array
    {
        $user = User::factory()->create();
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'status' => 'approved', 'is_active' => true, ...$productAttributes]);

        return [$user, $product];
    }

    private function sell(Product $product, int $returnDays = 30): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'total_cents' => 1000, 'delivery_address' => ['line1' => '1 A St', 'city' => 'B', 'state' => 'TX', 'postal_code' => '73301'],
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => 'SKU-1', 'quantity' => 1, 'unit_price_cents' => 1000, 'line_total_cents' => 1000, 'return_days' => $returnDays]);
    }

    // Buyers still under returns / warranty: the seller can't take it down (stock 0 shows Out of stock instead).
    public function test_seller_cannot_deactivate_or_remove_while_buyers_are_covered(): void
    {
        [$user, $product] = $this->sellerProduct();
        $this->sell($product);
        Sanctum::actingAs($user);

        $this->postJson("/api/seller/products/{$product->id}/active", ['active' => false])->assertStatus(422);
        $this->postJson("/api/seller/products/{$product->id}/request-deletion", [])->assertStatus(422);
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_seller_can_deactivate_when_nobody_is_covered(): void
    {
        [$user, $product] = $this->sellerProduct();
        $this->sell($product, 0);
        Sanctum::actingAs($user);

        $this->postJson("/api/seller/products/{$product->id}/active", ['active' => false])->assertOk();
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_return_policy_is_stored_tidy(): void
    {
        [, $product] = $this->sellerProduct();
        $product->update(['return_policy' => ['accepts' => ['defective', 'bogus', 'defective'], 'excludes' => ['liquid_damage'], 'notes' => '  ']]);
        $this->assertSame(['accepts' => ['defective'], 'excludes' => ['liquid_damage'], 'notes' => null], $product->fresh()->return_policy);

        $product->update(['return_policy' => ['accepts' => [], 'notes' => '']]);
        $this->assertNull($product->fresh()->return_policy);
    }

    // Once sold, the listing's identity is fixed (a different product is a new listing); price etc. can still change.
    public function test_a_sold_product_keeps_its_name_category_and_model(): void
    {
        [$user, $product] = $this->sellerProduct(['product_details' => ['model_number' => 'LS-100']]);
        $this->sell($product);
        Sanctum::actingAs($user);

        $this->patchJson("/api/seller/products/{$product->id}", ['name' => 'Something else entirely'])->assertStatus(422)->assertJsonFragment(['message' => 'This product has been sold, so its name can’t change — buyers’ orders and warranties refer to it. To sell a different product, add it as a new listing.']);
        $this->assertNotSame('Something else entirely', $product->fresh()->name);
        $this->assertTrue(\App\Support\ProductSnapshot::hasSold($product));
    }

    // Checkout freezes the product as bought on the order line.
    public function test_the_snapshot_records_the_product_as_bought(): void
    {
        [, $product] = $this->sellerProduct(['product_details' => ['warranty' => '1 year', 'warranty_terms' => 'Covers defects.'], 'return_days' => 15]);
        $snap = \App\Support\ProductSnapshot::of($product->fresh());
        $this->assertSame($product->name, $snap['name']);
        $this->assertSame('1 year', $snap['warranty']);
        $this->assertSame('Covers defects.', $snap['warranty_terms']);
        $this->assertSame(15, $snap['return_days']);
    }

    // Admins are emailed when a seller's edit to a live product waits for review (it stays live meanwhile).
    public function test_admins_are_emailed_about_a_held_edit(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        [$user, $product] = $this->sellerProduct(['country_of_origin' => 'China', 'image_url' => '/api/media/file/products/x.png', 'product_details' => ['model_number' => 'LS-1', 'power_source' => 'No power needed', 'warranty' => 'No warranty']]);
        $product->images()->create(['url' => '/api/media/file/products/x.png', 'sort_order' => 0]);
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($user);

        $res = $this->patchJson("/api/seller/products/{$product->id}", ['description' => 'Now with a FAQ']);
        if ($res->status() === 200) {
            $this->assertSame('approved', $product->fresh()->status);
            \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\AdminProductsSubmitted::class, fn ($n) => $n->edit === true);
        } else {
            $this->fail('Edit refused: '.json_encode($res->json()));
        }
    }

    // Only the change waits for approval; the product never goes offline for it.
    public function test_live_product_edit_stays_live_until_the_change_is_approved(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $complete = ['country_of_origin' => 'China', 'image_url' => '/api/media/file/products/x.png', 'product_details' => ['model_number' => 'LS-1', 'power_source' => 'No power needed', 'warranty' => 'No warranty']];
        [$user, $product] = $this->sellerProduct($complete);
        $product->images()->create(['url' => '/api/media/file/products/x.png', 'sort_order' => 0]);
        Sanctum::actingAs($user);

        // Default: stays live, change held.
        $this->patchJson("/api/seller/products/{$product->id}", ['description' => 'Better description'])->assertOk();
        $fresh = $product->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertSame('approved', $fresh->status);
        $this->assertNotNull($fresh->pending_changes);

        // Approved: the change goes live on the same, still-live product.
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/api/admin/products/{$product->id}/approve")->assertOk();
        $fresh = $product->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertSame('Better description', $fresh->description);
    }
}
