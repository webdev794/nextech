<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RiderApplication;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderHiringTest extends TestCase
{
    use RefreshDatabase;

    private function sellerStore(): Store
    {
        $seller = Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self', 'market' => 'US']);
        $address = $shop->addresses()->create(['name' => 'Shop', 'line1' => '5 Oak St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US', 'phone' => '5125550100', 'contact_name' => 'Pat Doe', 'is_default' => true]);
        $shop->forceFill(['local_delivery' => ['address_id' => $address->id, 'radius_km' => 8, 'fee_cents' => 0, 'days' => 1, 'lat' => 30.27, 'lng' => -97.74]])->save();

        return Store::where('shop_id', $shop->id)->first();
    }

    private function application(Store $store, User $user, array $extra = []): array
    {
        return $extra + [
            'store_ids' => [$store->id], 'education' => 'High school', 'work_history' => 'Courier, 2 years', 'health_issue' => false, 'consent_removal' => true,
            'photo_path' => "kyc/{$user->id}/photo.jpg", 'phone' => '5125550111', 'email' => 'rider@example.com', 'date_of_birth' => now()->subYears(25)->toDateString(),
            'home_address' => '9 Elm St, Austin, TX', 'home_lat' => 30.28, 'home_lng' => -97.75, 'vehicle_type' => 'bicycle', 'own_vehicle' => true, 'experience_months' => 18,
            'id_document_path' => "kyc/{$user->id}/id.pdf",
        ];
    }

    public function test_a_seller_hires_riders_for_their_store(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $store = $this->sellerStore();
        $seller = $store->shop->seller->user;

        // Not hiring yet: the store isn't offered and the footer link stays off.
        $applicant = User::factory()->create();
        Sanctum::actingAs($applicant);
        $this->getJson('/api/rider-application/stores')->assertOk()->assertJsonCount(0, 'data.stores');
        $this->getJson('/api/rider-application/hiring')->assertOk()->assertJsonPath('data.open', false);

        // The seller opens hiring (local delivery is on).
        Sanctum::actingAs($seller);
        $this->patchJson('/api/seller/local-delivery/hiring', ['open' => true])->assertOk()->assertJsonPath('data.store.hiring_open', true);

        Sanctum::actingAs($applicant);
        $this->getJson('/api/rider-application/stores')->assertOk()->assertJsonPath('data.stores.0.seller', 'Acme')->assertJsonPath('data.stores.0.min_age', 18);
        // Under the minimum age, no own vehicle, or no ID: refused.
        $this->postJson('/api/rider-application', $this->application($store, $applicant, ['date_of_birth' => now()->subYears(17)->toDateString()]))->assertStatus(422);
        $this->postJson('/api/rider-application', $this->application($store, $applicant, ['own_vehicle' => false]))->assertStatus(422);
        $this->postJson('/api/rider-application', $this->application($store, $applicant, ['id_document_path' => null]))->assertStatus(422);
        $this->postJson('/api/rider-application', $this->application($store, $applicant, ['consent_removal' => false]))->assertStatus(422);
        $this->postJson('/api/rider-application', $this->application($store, $applicant, ['vehicle_type' => 'scooter', 'license_number' => 'DL1', 'license_document_path' => "kyc/{$applicant->id}/dl.pdf"]))->assertStatus(422); // no RC
        $this->postJson('/api/rider-application', $this->application($store, $applicant))->assertCreated();
        $this->assertDatabaseHas('support_messages', ['body' => "{$applicant->name} applied to deliver for your store. Review it in Seller Center → Local delivery → Applications."]);

        // The seller sees it (with documents) and accepts: the applicant becomes their rider.
        Sanctum::actingAs($seller->fresh());
        $app = $this->getJson('/api/seller/local-delivery')->assertOk()->assertJsonPath('data.applications.0.age', 25)->json('data.applications.0');
        $this->assertSame("kyc/{$applicant->id}/id.pdf", $app['documents']['ID proof']);
        // The seller's accept is a recommendation: the store's admin approves.
        $this->postJson("/api/seller/rider-applications/{$app['id']}/approve")->assertOk()
            ->assertJsonPath('data.applications.0.status', 'seller_accepted')->assertJsonCount(0, 'data.riders');
        $this->assertFalse((bool) $applicant->fresh()->is_rider);
        Notification::assertSentTo($admin, \App\Notifications\AdminNotice::class);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/rider-applications')->assertOk()->assertJsonPath('data.0.status', 'seller_accepted');
        $this->postJson("/api/admin/rider-applications/{$app['id']}/approve")->assertOk();
        $this->assertTrue($applicant->fresh()->is_rider);
        $this->assertDatabaseHas('support_messages', ['body' => "{$applicant->name} is approved and now delivers for your store."]);
        Sanctum::actingAs($seller->fresh());
        $this->getJson('/api/seller/local-delivery')->assertOk()->assertJsonPath('data.riders.0.name', $applicant->name);
        // Their rider profile comes from the application.
        $this->assertSame(["kyc/{$applicant->id}/photo.jpg", '9 Elm St, Austin, TX', $app['id']], [$applicant->fresh()->rider_photo_path, $applicant->fresh()->rider_base_address, $applicant->fresh()->rider_application_id]);
        $this->assertTrue(RiderApplication::find($app['id'])->decided_by_seller);

        // Removing the rider is blocked while they have an open order from the store.
        $order = Order::query()->forceCreate(['user_id' => User::factory()->create()->id, 'store_id' => $store->id, 'delivery_partner_id' => $applicant->id, 'status' => 'out_for_delivery', 'total_cents' => 1000, 'subtotal_cents' => 1000, 'delivery_address' => ['line1' => 'x']]);
        $this->postJson("/api/seller/riders/{$applicant->id}/remove", ['reason' => 'Late'])->assertStatus(422);
        $order->forceFill(['status' => 'delivered'])->save();
        $this->postJson("/api/seller/riders/{$applicant->id}/remove")->assertStatus(422); // a reason is needed
        $this->postJson("/api/seller/riders/{$applicant->id}/remove", ['reason' => 'Late deliveries'])->assertOk()->assertJsonCount(0, 'data.riders');
        Notification::assertSentTo($admin, \App\Notifications\AdminNotice::class);
    }

    public function test_admin_unlinking_a_sellers_rider_needs_a_reason_and_tells_the_seller(): void
    {
        $store = $this->sellerStore();
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        $rider->stores()->attach($store->id);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/riders/{$rider->id}", ['store_ids' => []])->assertStatus(422);
        $this->patchJson("/api/admin/riders/{$rider->id}", ['store_ids' => [], 'reason' => 'Moved away'])->assertOk();
        $this->assertDatabaseHas('support_messages', ['body' => "{$rider->name} no longer delivers for your store: Moved away"]);
    }

    public function test_rider_notice_reaches_admin_and_stays_until_processed(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();

        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/notice', ['reason' => 'Moving city'])->assertOk()->assertJsonPath('data.notice.leaving_on', now()->addDays(30)->toDateString());
        $this->postJson('/api/rider/notice')->assertStatus(422);
        Notification::assertSentTo($admin, \App\Notifications\AdminNotice::class);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonPath('data.rider_notices.0.due', false);
        $this->travel(31)->days();
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonPath('data.rider_notices.0.due', true);
        $this->postJson("/api/admin/riders/{$rider->id}/notice-processed")->assertOk();
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonCount(0, 'data.rider_notices');
        $this->assertFalse($rider->fresh()->rider_is_active);
    }

    public function test_a_seller_gives_own_delivery_orders_to_their_rider(): void
    {
        Notification::fake();
        $store = $this->sellerStore();
        $shop = $store->shop;
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        $rider->stores()->attach($store->id);
        $other = User::factory()->create();
        $other->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();

        $buyer = User::factory()->create();
        $order = Order::query()->forceCreate(['user_id' => $buyer->id, 'market' => 'US', 'status' => 'processing', 'payment_method' => 'cod', 'subtotal_cents' => 2500, 'total_cents' => 2500,
            'delivery_address' => ['name' => 'Bea Buyer', 'phone' => '5125550199', 'line1' => '7 Pine St', 'city' => 'Austin', 'postal_code' => '73301']]);
        $product = \App\Models\Product::query()->forceCreate(['name' => 'Earbuds', 'slug' => 'earbuds', 'sku' => 'EB-1', 'price_cents' => 2500, 'shop_id' => $shop->id]);
        \App\Models\OrderItem::query()->forceCreate(['order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id, 'fulfilled_by' => 'seller', 'product_name' => 'Earbuds', 'sku' => 'EB-1', 'quantity' => 1, 'unit_price_cents' => 2500, 'line_total_cents' => 2500]);
        \App\Models\OrderShopShipping::query()->forceCreate(['order_id' => $order->id, 'shop_id' => $shop->id, 'mode' => 'self', 'method' => 'local', 'fee_cents' => 0, 'transit_min_days' => 1, 'transit_max_days' => 1, 'ship_by' => now()->addDay(), 'deliver_from' => now()->addDay(), 'deliver_by' => now()->addDays(2)]);

        $this->travel(1)->hours(); // past the pending half hour
        Sanctum::actingAs($shop->seller->user);
        $this->postJson("/api/seller/fulfillment/orders/{$order->id}/local-dispatch", ['rider_id' => $other->id])->assertStatus(422); // not their rider
        $this->postJson("/api/seller/fulfillment/orders/{$order->id}/local-dispatch", ['rider_id' => $rider->id])->assertCreated();
        $package = \App\Models\OrderPackage::where('order_id', $order->id)->first();
        $this->assertSame($rider->id, $package->rider_id);
        Notification::assertSentTo($rider, \App\Notifications\RiderNotice::class);

        // The rider sees it (with the buyer's details and cash to collect) and delivers it with the code.
        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/packages')->assertOk()->assertJsonPath('data.0.buyer', 'Bea Buyer')->assertJsonPath('data.0.cash_cents', 2500);
        $this->postJson("/api/rider/packages/{$package->id}/deliver", ['delivery_code' => '0000', 'cash_collected' => true])->assertStatus(422);
        $this->postJson("/api/rider/packages/{$package->id}/deliver", ['delivery_code' => $package->delivery_code])->assertStatus(422); // cash not ticked

        // Admin only watches the seller's own delivery.
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/packages/{$package->id}", ['status' => 'delivered'])->assertStatus(422);

        $store->forceFill(['rider_cash_limit_cents' => 1000])->save(); // a low limit, so this cash order goes over it
        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/packages/{$package->id}/deliver", ['delivery_code' => $package->delivery_code, 'cash_collected' => true])->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(['delivered', 'rider'], [$package->fresh()->status, $package->fresh()->delivered_by]);
        $this->assertNotNull($package->fresh()->cash_collected_at);

        // Over the store's cash limit: paused for this store, and told to hand it over.
        $this->getJson('/api/rider/packages')->assertOk()->assertJsonPath('cash.0.held_cents', 2500)->assertJsonPath('cash.0.paused', true);
        $this->assertDatabaseHas('support_messages', ['body' => "{$rider->name} collected $25.00 on order #{$order->id} and now holds $25.00 of your cash. Mark it received in Seller Center → Local delivery when they hand it over."]);

        // The seller can't give them more orders until the cash is received — or chooses Later (own risk).
        Sanctum::actingAs($shop->seller->user->fresh());
        $this->getJson('/api/seller/local-delivery')->assertOk()->assertJsonPath('data.riders.0.cash_held_cents', 2500)->assertJsonPath('data.riders.0.cash_paused', true);
        $this->expectsPauseRefusal($shop, $rider);
        // Paused in every store: the Rider app says why, and the store's own orders can't be claimed either.
        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/orders')->assertOk()->assertJsonPath('data.cash_block', fn ($v) => str_contains((string) $v, 'Acme'));
        Sanctum::actingAs($shop->seller->user->fresh());
        $this->postJson("/api/seller/riders/{$rider->id}/cash-later")->assertOk()->assertJsonPath('data.riders.0.cash_paused', false)->assertJsonPath('data.riders.0.cash_later', true);
        $this->postJson("/api/seller/riders/{$rider->id}/cash-received")->assertOk()->assertJsonPath('data.riders.0.cash_held_cents', 0);
        $this->assertNotNull($package->fresh()->cash_handed_over_at);
    }

    private function expectsPauseRefusal(Shop $shop, User $rider): void
    {
        try {
            \App\Support\SellerRiders::riderFor($shop, $rider->id);
            $this->fail('A rider paused for cash should not get new orders.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertStringContainsString('hand over the cash', $e->getMessage());
        }
    }

    public function test_cash_on_delivery_only_up_to_the_maximum_order(): void
    {
        // Default: up to $200 in the US (the most a rider may carry).
        $this->assertNull(\App\Support\SellerProgress::codBlockedReason([], 'US', 15000));
        $this->assertStringContainsString('up to $200.00', (string) \App\Support\SellerProgress::codBlockedReason([], 'US', 25000));

        // Admin can raise it (at their own risk); a seller can only lower it for their own orders.
        \App\Models\Setting::put('cod_max_order', ['US' => 50000]);
        $this->assertNull(\App\Support\SellerProgress::codBlockedReason([], 'US', 25000));
        $store = $this->sellerStore();
        $store->forceFill(['rider_cash_limit_cents' => 10000])->save();
        $this->assertSame(10000, \App\Support\SellerRiderCash::codMaxCents('US', $store->shop));
    }

    public function test_rider_money_pay_per_delivery_cash_offset_and_settlement(): void
    {
        $store = $this->sellerStore();
        $store->forceFill(['rider_pay_cents' => 400])->save();
        $shop = $store->shop;
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        $rider->stores()->attach($store->id);
        $order = Order::query()->forceCreate(['user_id' => User::factory()->create()->id, 'market' => 'US', 'status' => 'processing', 'payment_method' => 'cod', 'subtotal_cents' => 2500, 'total_cents' => 2500, 'delivery_address' => ['line1' => 'x']]);
        $package = \App\Models\OrderPackage::query()->forceCreate(['order_id' => $order->id, 'shop_id' => $shop->id, 'rider_id' => $rider->id, 'carrier' => \App\Support\SellerShipping::LOCAL, 'tracking_number' => 'NT-1-L1', 'label_source' => 'local', 'status' => 'delivered', 'shipped_at' => now(), 'delivered_at' => now(), 'cash_collected_at' => now()]);

        // Paid per delivery at the seller's rate: the rider is credited, the seller charged — once.
        \App\Support\RiderMoney::creditSellerDelivery($package);
        \App\Support\RiderMoney::creditSellerDelivery($package);
        $this->assertSame(400, \App\Support\RiderLedger::balanceCents($rider));
        $this->assertSame(-400, (int) \App\Models\SellerLedgerEntry::where('shop_id', $shop->id)->where('type', 'rider_pay')->sum('amount_cents'));

        // Settlement: earnings minus cash held ($4 − $25 = they owe $21).
        $this->assertSame(-2100, \App\Support\RiderMoney::settlement($rider)['net_cents']);

        // Earnings don't cover the $25 yet: nothing moves. With $30 earned, the $25 goes to the seller.
        $this->assertSame(0, \App\Support\RiderMoney::offsetSellerCash($rider));
        \App\Models\RiderLedgerEntry::create(['user_id' => $rider->id, 'type' => 'adjustment', 'amount_cents' => 2600, 'note' => 'test']);
        $this->assertSame(2500, \App\Support\RiderMoney::offsetSellerCash($rider));
        $this->assertSame(500, \App\Support\RiderLedger::balanceCents($rider));
        $this->assertSame(2500, (int) \App\Models\SellerLedgerEntry::where('shop_id', $shop->id)->where('type', 'rider_cash_recovered')->sum('amount_cents'));
        $this->assertNotNull($package->fresh()->cash_handed_over_at);

        // The money table (the seller's view of their store).
        $row = \App\Support\RiderMoney::table(now(), $store)[0];
        $this->assertSame([1, 400, 2500, 0], [$row['deliveries'], $row['earned_cents'], $row['cash_collected_cents'], $row['cash_held_cents']]);
        $this->assertNull($row['balance_cents']);
    }

    public function test_rider_claims_cash_handover_and_only_the_seller_confirms_it(): void
    {
        $store = $this->sellerStore();
        $shop = $store->shop;
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        $rider->stores()->attach($store->id, ['cash_paused_at' => now()]);
        $make = function () use ($shop, $rider) {
            $order = Order::query()->forceCreate(['user_id' => User::factory()->create()->id, 'market' => 'US', 'status' => 'processing', 'payment_method' => 'cod', 'subtotal_cents' => 2500, 'total_cents' => 2500, 'delivery_address' => ['line1' => 'x']]);

            return \App\Models\OrderPackage::query()->forceCreate(['order_id' => $order->id, 'shop_id' => $shop->id, 'rider_id' => $rider->id, 'carrier' => \App\Support\SellerShipping::LOCAL, 'tracking_number' => 'NT-'.$order->id, 'label_source' => 'local', 'status' => 'delivered', 'shipped_at' => now(), 'delivered_at' => now(), 'cash_collected_at' => now()]);
        };
        $first = $make();

        // The rider says it's handed over: unpaused meanwhile; it waits for the seller — never auto-confirmed.
        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/stores/{$store->id}/cash-handed")->assertOk()->assertJsonPath('cash.0.claimed', true);
        $this->assertFalse(\App\Support\SellerRiderCash::paused($rider, $store));
        $this->travel(3)->days();
        $this->assertNull($first->fresh()->cash_handed_over_at);

        // The seller confirms.
        Sanctum::actingAs($shop->seller->user);
        $this->getJson('/api/seller/local-delivery')->assertOk()->assertJsonPath('data.riders.0.cash_claimed_cents', 2500);
        $this->postJson("/api/seller/riders/{$rider->id}/cash-received")->assertOk();
        $this->assertNotNull($first->fresh()->cash_handed_over_at);

        // Another one the seller marks Not received: the rider still holds it, is paused, and it comes from their earnings.
        $second = $make();
        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/stores/{$store->id}/cash-handed")->assertOk();
        Sanctum::actingAs($shop->seller->user->fresh());
        $this->postJson("/api/seller/riders/{$rider->id}/cash-not-received", ['reason' => 'Never got it'])->assertOk()->assertJsonPath('data.riders.0.cash_paused', true);
        $this->assertSame(2500, \App\Support\SellerRiderCash::heldCents($rider, $store));
        \App\Models\RiderLedgerEntry::create(['user_id' => $rider->id, 'type' => 'adjustment', 'amount_cents' => 5000, 'note' => 'test']);
        $this->assertSame(2500, \App\Support\RiderMoney::offsetSellerCash($rider));
        $this->assertNotNull($second->fresh()->cash_handed_over_at);
    }

    public function test_a_rider_works_in_one_country_only(): void
    {
        $sellerStore = $this->sellerStore(); // US
        $india = Store::query()->forceCreate(['name' => 'Delhi hub', 'line1' => '1 MG Rd', 'city' => 'Delhi', 'state' => 'DL', 'postal_code' => '110001', 'country' => 'IN', 'delivery_radius_km' => 5, 'is_active' => true]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $rider = User::factory()->create();
        $rider->forceFill(['is_rider' => true, 'rider_is_active' => true])->save();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/riders/{$rider->id}", ['store_ids' => [$sellerStore->id, $india->id]])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'one country'));
    }
}
