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
}
