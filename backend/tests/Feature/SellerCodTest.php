<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\SellerLedgerEntry;
use App\Models\Shop;
use App\Models\User;
use App\Support\SellerCod;
use App\Support\SellerProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerCodTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        $seller = Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);

        return Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self', 'accepts_cod' => true, 'market' => 'US']);
    }

    // Each seller's cash on delivery is the admin's switch (off until allowed), and pauses when they owe too much.
    public function test_admin_switches_cash_on_delivery_per_seller_and_the_owed_limit_pauses_it(): void
    {
        $shop = $this->shop();
        $this->assertNotNull(SellerCod::sellerReason($shop));
        $this->assertNotNull(SellerProgress::codBlockedReason([\App\Models\Product::factory()->make(['shop_id' => $shop->id])->setRelation('shop', $shop)], 'US'));

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/api/admin/sellers/{$shop->seller_id}/cod", ['approved' => true])->assertOk()->assertJsonPath('data.cod.available', true);
        $this->assertNull(SellerCod::sellerReason($shop->fresh()));

        // The seller kept $150 cash: they owe more than the $100 default limit — paused, and the admin sees it.
        SellerLedgerEntry::create(['shop_id' => $shop->id, 'order_id' => null, 'type' => 'cod_cash_held', 'amount_cents' => -15000, 'note' => 'cash kept']);
        $this->assertStringContainsString('owe NexTech', (string) SellerCod::sellerReason($shop->fresh()));
        $this->getJson('/api/admin/notifications')->assertOk()
            ->assertJsonPath('data.sellers_owing.0.owed_cents', 15000)
            ->assertJsonPath('data.sellers_owing.0.over_limit', true)
            ->assertJsonPath('data.cod_kept.0.amount_cents', 15000);

        $this->postJson("/api/admin/sellers/{$shop->seller_id}/cod", ['approved' => false])->assertOk();
        $this->assertFalse($shop->fresh()->accepts_cod);
    }
}
