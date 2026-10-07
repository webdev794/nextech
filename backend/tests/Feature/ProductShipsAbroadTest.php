<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// A product from a shop that ships to India is sold there only if the seller left "ship abroad" on.
class ProductShipsAbroadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_products_marked_ships_abroad_are_offered_abroad(): void
    {
        $user = User::factory()->create();
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'market' => 'US', 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self',
            'intl_shipping' => ['IN' => ['fee_cents' => 1500, 'transit_min_days' => 7, 'transit_max_days' => 14]]]);
        $abroad = Product::factory()->create(['shop_id' => $shop->id, 'market' => 'US', 'status' => 'approved', 'ships_abroad' => true, 'intl_extra_fee_cents' => 500]);
        $homeOnly = Product::factory()->create(['shop_id' => $shop->id, 'market' => 'US', 'status' => 'approved', 'ships_abroad' => false]);

        $ids = Product::query()->availableIn('IN')->pluck('id')->all();
        $this->assertContains($abroad->id, $ids);
        $this->assertNotContains($homeOnly->id, $ids);
        $this->assertNull($homeOnly->crossBorderTerms('IN'));
    }
}
