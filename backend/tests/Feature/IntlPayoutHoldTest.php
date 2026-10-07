<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use App\Support\SellerLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Money from an order shipped abroad is held until delivery plus the longer of
// the return window and the product's warranty; at home, the return window only.
class IntlPayoutHoldTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrder(string $orderMarket): array
    {
        $user = User::factory()->create();
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'market' => 'US', 'name' => 'Acme', 'slug' => 'acme-'.strtolower($orderMarket), 'is_active' => true]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'market' => 'US', 'product_details' => ['warranty' => '1 year']]);
        $order = Order::create(['user_id' => User::factory()->create()->id, 'market' => $orderMarket, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'total_cents' => 1000, 'delivered_at' => now()->subDay(), 'delivery_address' => ['line1' => '1 A St', 'city' => 'B', 'state' => 'TX', 'postal_code' => '73301']]);
        $order->items()->create(['product_id' => $product->id, 'shop_id' => $shop->id, 'fulfilled_by' => 'nextech', 'product_name' => $product->name, 'sku' => 'S1', 'quantity' => 1, 'unit_price_cents' => 1000, 'line_total_cents' => 1000, 'return_days' => 30]);

        return [$order->fresh(), $shop];
    }

    public function test_abroad_is_held_through_warranty_home_through_returns_only(): void
    {
        [$home, $shop] = $this->deliveredOrder('US');
        $this->assertTrue(SellerLedger::releaseDate($home, $shop->id)->lt(now()->addDays(31)));

        [$abroad, $shop2] = $this->deliveredOrder('IN');
        $this->assertTrue(SellerLedger::releaseDate($abroad, $shop2->id)->gt(now()->addMonths(11)));
    }
}
