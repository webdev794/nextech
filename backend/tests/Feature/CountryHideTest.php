<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Hiding a country: admin sees what's still open there first; its sellers see "selling is paused".
class CountryHideTest extends TestCase
{
    use RefreshDatabase;

    private function indianSeller(): array
    {
        $user = User::factory()->create();
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'country' => 'IN', 'business_type' => 'individual', 'company_name' => 'Desi', 'tax_id' => 'ABCDE1234F',
            'registered_line1' => '1 MG Rd', 'registered_city' => 'Pune', 'registered_state' => 'MH', 'registered_postal_code' => '411001', 'registered_country' => 'IN',
            'contact_name' => 'Ravi', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'market' => 'IN', 'name' => 'Desi', 'slug' => 'desi', 'is_active' => true]);

        return [$user, $shop];
    }

    public function test_admin_sees_what_is_open_before_hiding_and_seller_sees_paused(): void
    {
        Setting::put('active_countries', ['US', 'IN']);
        [$sellerUser, $shop] = $this->indianSeller();
        Order::create(['user_id' => User::factory()->create()->id, 'market' => 'IN', 'status' => 'confirmed', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'total_cents' => 1000, 'delivery_address' => ['line1' => '1 A St', 'city' => 'Pune', 'state' => 'MH', 'postal_code' => '411001']]);
        $shop->ledgerEntries()->create(['type' => 'sale', 'amount_cents' => 5000, 'note' => 'Sale']);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/countries/IN/impact')->assertOk()
            ->assertJsonPath('data.open_orders', 1)->assertJsonPath('data.sellers', 1)->assertJsonPath('data.seller_balance_cents', 5000);

        Sanctum::actingAs($sellerUser);
        $this->getJson('/api/seller/me')->assertOk()->assertJsonPath('data.market_paused', false);
        Setting::put('active_countries', ['US']);
        $this->getJson('/api/seller/me')->assertOk()->assertJsonPath('data.market_paused', true);
    }
}
