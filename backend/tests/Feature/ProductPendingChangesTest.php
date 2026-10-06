<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use App\Support\ProductPendingChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// A seller's edit to a live product waits for review; the live version keeps selling.
class ProductPendingChangesTest extends TestCase
{
    use RefreshDatabase;

    private function liveSellerProduct(): Product
    {
        $user = User::factory()->create();
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

        return Product::factory()->create(['shop_id' => $shop->id, 'status' => 'approved', 'is_active' => true, 'name' => 'Old name', 'price_cents' => 1000, 'inventory_quantity' => 5]);
    }

    public function test_held_edit_keeps_the_live_version_and_applies_stock_now(): void
    {
        $product = $this->liveSellerProduct();

        ProductPendingChanges::hold($product, ['name' => 'New name', 'price_cents' => 1500, 'inventory_quantity' => 9, 'status' => 'pending'], null, null);

        $product->refresh();
        $this->assertSame('approved', $product->status);
        $this->assertSame('Old name', $product->name);
        $this->assertSame(1000, $product->price_cents);
        $this->assertSame(9, $product->inventory_quantity); // stock isn't reviewed
        $this->assertSame('New name', ProductPendingChanges::overlay($product->toArray())['name']);
    }

    public function test_admin_approve_applies_and_reject_drops_the_edit(): void
    {
        $product = $this->liveSellerProduct();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        ProductPendingChanges::hold($product, ['name' => 'New name'], null, null);
        $this->postJson("/api/admin/products/{$product->id}/approve")->assertOk();
        $this->assertSame('New name', $product->fresh()->name);
        $this->assertNull($product->fresh()->pending_changes);

        ProductPendingChanges::hold($product->fresh(), ['name' => 'Bad name'], null, null);
        $this->postJson("/api/admin/products/{$product->id}/reject", ['reason' => 'Name is misleading'])->assertOk();
        $this->assertSame('New name', $product->fresh()->name);
        $this->assertSame('approved', $product->fresh()->status);
        $this->assertNull($product->fresh()->pending_changes);
    }
}
