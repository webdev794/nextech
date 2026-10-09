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

    public function test_store_work_hours_remind_riders_and_report_who_is_missing(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $store = \App\Models\Store::query()->forceCreate(['name' => 'Hub', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US', 'delivery_radius_km' => 5, 'is_active' => true,
            'rider_hours' => ['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '18:00']]);
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        $rider->stores()->attach($store->id);

        $this->travelTo(Carbon::parse('2026-10-13 09:05')); // a Tuesday
        $this->assertSame(1, \App\Support\RiderWorkHours::check());
        \Illuminate\Support\Facades\Notification::assertSentTo($rider, \App\Notifications\RiderNotice::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($admin, \App\Notifications\AdminNotice::class);

        $this->travelTo(Carbon::parse('2026-10-13 09:35'));
        $this->assertSame(0, \App\Support\RiderWorkHours::check()); // reminded once a day
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\AdminNotice::class);

        $this->travelTo(Carbon::parse('2026-10-17 10:00')); // Saturday: not a working day
        $this->assertFalse(\App\Support\RiderWorkHours::isWorkTime($store->fresh()));
    }

    public function test_excellent_month_suggests_a_bonus_to_admin(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        \App\Models\Setting::put('rider_bonus', ['US' => ['benchmark_cents' => 1000, 'min_deliveries' => 1, 'min_rating' => 4.8]]);
        $store = \App\Models\Store::query()->forceCreate(['name' => 'Hub', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US', 'delivery_radius_km' => 5, 'is_active' => true]);
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        $rider->stores()->attach($store->id);
        $buyer = User::factory()->create();

        $this->travelTo(Carbon::parse('2026-09-15 12:00'));
        $order = \App\Models\Order::query()->forceCreate(['user_id' => $buyer->id, 'market' => 'US', 'status' => 'completed', 'payment_method' => 'card', 'payment_status' => 'paid', 'subtotal_cents' => 2500, 'total_cents' => 2500, 'delivery_partner_id' => $rider->id, 'delivered_at' => now(), 'delivery_address' => ['line1' => 'x']]);
        \App\Models\RiderLedgerEntry::create(['user_id' => $rider->id, 'type' => 'delivery_credit', 'amount_cents' => 5000, 'note' => 'test']);
        \App\Models\RiderReview::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'user_id' => $buyer->id, 'rating' => 5, 'source' => 'order']);

        $this->travelTo(Carbon::parse('2026-09-25 06:30'));
        $this->assertSame(1, \App\Support\RiderBonus::suggest());
        $this->assertSame(0, \App\Support\RiderBonus::suggest()); // once per rider per month
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\AdminNotice::class);
        Sanctum::actingAs($admin);
        $id = $this->getJson('/api/admin/notifications')->assertOk()->json('data.rider_bonus_suggestions.0.id');
        $this->deleteJson("/api/admin/rider-bonus-suggestions/{$id}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_limits_the_days_sellers_may_promise(): void
    {
        \App\Models\Setting::put('seller_max_local_days', 2);
        \App\Models\Setting::put('seller_max_courier_days', 10);
        $this->assertSame(2, \App\Support\SellerShipping::maxLocalDays());
        $this->assertSame(10, \App\Support\SellerShipping::maxCourierDays());
        $shop = $this->shop();
        Sanctum::actingAs($shop->seller->user);
        $this->getJson('/api/seller/shipping')->assertOk()->assertJsonPath('data.max_local_days', 2)->assertJsonPath('data.max_courier_days', 10);
    }
}
