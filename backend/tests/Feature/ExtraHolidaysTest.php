<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\Shop;
use App\Models\User;
use App\Support\ExtraHolidays;
use App\Support\SellerShipping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExtraHolidaysTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        $seller = Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);

        return Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self', 'market' => 'US']);
    }

    public function test_seller_asks_for_a_day_off_and_admin_adds_it_for_the_store(): void
    {
        $shop = $this->shop();
        $date = now()->addDays(10)->toDateString();
        Sanctum::actingAs($shop->seller->user);
        $this->postJson('/api/seller/shipping/holiday-requests', ['date' => $date, 'name' => 'Family event'])->assertOk()->assertJsonPath('data.holiday_requests.0.name', 'Family event');

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);
        $id = $this->getJson('/api/admin/notifications')->assertOk()->json('data.holiday_requests.0.id');
        $this->postJson("/api/admin/holiday-requests/{$id}", ['decision' => 'store'])->assertOk()->assertJsonPath('data.extras.0.shop_id', $shop->id);

        // Always off for this store, even on a weekday; other shops unaffected.
        $this->assertFalse(SellerShipping::isWorkingDay($shop->fresh(), Carbon::parse($date)));
        $this->assertSame([], ExtraHolidays::requests());
    }

    public function test_country_holiday_is_tickable_by_sellers(): void
    {
        $shop = $this->shop();
        $monday = Carbon::parse('next monday')->addWeeks(2);
        ExtraHolidays::add('US', $monday->toDateString(), 'Local fair');
        $this->assertFalse(SellerShipping::isWorkingDay($shop, $monday)); // off by default
        $shop->forceFill(['working_holidays' => ['x'.$monday->toDateString()]])->save();
        $this->assertTrue(SellerShipping::isWorkingDay($shop->fresh(), $monday)); // seller works it
        Sanctum::actingAs($shop->seller->user);
        $this->getJson('/api/seller/shipping')->assertOk()->assertJsonFragment(['name' => 'Local fair']);
    }

    public function test_riders_of_a_closed_store_get_the_day_off_and_back_next_day(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $shop = $this->shop();
        $store = \App\Support\SellerStores::ensure($shop);
        $store->forceFill(['local_delivery_active' => true])->save();
        $own = \App\Models\Store::query()->forceCreate(['name' => 'Hub', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US', 'delivery_radius_km' => 5, 'is_active' => true]);
        [$only, $both] = [User::factory()->create(), User::factory()->create()];
        foreach ([$only, $both] as $r) {
            $r->forceFill(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true])->save();
            $r->stores()->attach($store->id);
        }
        $both->stores()->attach($own->id);

        $this->travelTo(Carbon::parse('2026-12-24 18:00')); // Christmas tomorrow (a Friday)
        $this->assertSame(1, \App\Support\StoreDaysOff::warnTomorrow());
        \Illuminate\Support\Facades\Notification::assertSentTo($only, \App\Notifications\RiderNotice::class);

        $this->travelTo(Carbon::parse('2026-12-25 00:15'));
        $this->assertSame(1, \App\Support\StoreDaysOff::markRiders());
        $this->assertFalse((bool) $only->fresh()->rider_available); // only that store → day off
        $this->assertTrue((bool) $both->fresh()->rider_available);  // NexTech's store is open

        $this->travelTo(Carbon::parse('2026-12-28 00:15')); // Monday: open again
        \App\Support\StoreDaysOff::markRiders();
        $this->assertTrue((bool) $only->fresh()->rider_available);
    }

    public function test_admin_can_allow_seller_cash_on_delivery_only_for_local_deliveries(): void
    {
        $shop = $this->shop();
        \App\Models\Setting::put('seller_cod_mode', 'local');
        $this->assertNotNull(\App\Support\SellerCod::sellerReason($shop)); // local delivery not on yet
        $shop->forceFill(['accepts_cod' => true, 'local_delivery' => ['address_id' => 1, 'radius_km' => 5, 'fee_cents' => 0, 'days' => 1, 'lat' => 30.27, 'lng' => -97.74]])->save();
        $this->assertNull(\App\Support\SellerCod::sellerReason($shop->fresh()));
        $product = \App\Models\Product::query()->forceCreate(['name' => 'Earbuds', 'slug' => 'earbuds', 'sku' => 'EB-1', 'price_cents' => 2500, 'shop_id' => $shop->id]);
        $product->load('shop');
        $this->assertNull(\App\Support\SellerProgress::codBlockedReason([$product], 'US', 2500, true));
        $this->assertStringContainsString('only for buyers near their shop', (string) \App\Support\SellerProgress::codBlockedReason([$product], 'US', 2500, false));
    }
}
