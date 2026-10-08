<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\Shop;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminLocalDeliveryOff;
use App\Notifications\RiderNotice;
use App\Support\SellerShipping;
use App\Support\SellerStores;
use App\Support\StoreLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerStoresTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /** Own delivery on, from a ship-from address in Austin. */
    private function localOn(Shop $shop): Store
    {
        $address = $shop->addresses()->create(['name' => 'Shop', 'line1' => '5 Oak St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US', 'phone' => '5125550100', 'contact_name' => 'Pat Doe', 'is_default' => true]);
        $shop->forceFill(['local_delivery' => ['address_id' => $address->id, 'radius_km' => 7.5, 'fee_cents' => 0, 'days' => 1, 'lat' => 30.27, 'lng' => -97.74]])->save();

        return Store::where('shop_id', $shop->id)->first();
    }

    private function rider(Store $store): User
    {
        $rider = User::factory()->create();
        $rider->stores()->attach($store->id);

        return $rider;
    }

    public function test_every_seller_has_a_store_and_nextech_delivery_never_uses_it(): void
    {
        $shop = $this->shop();
        $store = Store::where('shop_id', $shop->id)->first();
        $this->assertNotNull($store, 'a new seller shop gets its store');

        // Shown in Stores / hubs only once the seller is approved.
        $shop->seller->forceFill(['status' => 'pending'])->save();
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/stores')->assertOk()->assertJsonCount(0, 'data');
        $shop->seller->forceFill(['status' => 'approved'])->save();
        $this->assertSame(['1 Main St', false, 'off'], [$store->line1, $store->is_active, SellerStores::status($store)]);

        Sanctum::actingAs($this->admin());
        $own = $this->postJson('/api/admin/stores', ['name' => 'Main hub', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'latitude' => 30.27, 'longitude' => -97.74, 'delivery_radius_km' => 10])->assertCreated()->json('data');

        $this->getJson('/api/admin/stores')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.kind', 'own')->assertJsonPath('data.1.kind', 'seller')->assertJsonPath('data.1.seller_id', $shop->seller_id)->assertJsonPath('data.1.local_delivery_status', 'off');
        $this->getJson('/api/admin/stores?kind=seller')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shop.name', 'Acme');

        // NexTech's checkout area only sees its own store, even where a seller's store is on.
        $store = $this->localOn($shop);
        $this->assertSame(['5 Oak St', 8, true], [$store->line1, $store->delivery_radius_km, $store->is_active]);
        $this->assertSame([$own['id']], StoreLocator::locatedStores()->pluck('id')->all());
        Store::find($own['id'])->update(['is_active' => false]);
        $this->assertNull(StoreLocator::servingStore(30.27, -97.74));

        // A new own store starts with riders off; turning them on needs a rider linked.
        $this->assertFalse(Store::find($own['id'])->local_delivery_active);
        $this->patchJson("/api/admin/stores/{$own['id']}/local-delivery", ['action' => 'on'])->assertStatus(422);
        $this->rider(Store::find($own['id']));
        $this->patchJson("/api/admin/stores/{$own['id']}/local-delivery", ['action' => 'on'])->assertOk();
        $this->getJson('/api/admin/stores?kind=own')->assertOk()->assertJsonPath('data.0.local_delivery_status', 'on');
    }

    public function test_seller_turning_off_with_riders_waits_for_admin_and_riders_hear_only_on_approval(): void
    {
        Notification::fake();
        $shop = $this->shop();
        $store = $this->localOn($shop);
        $buyer = fn () => [30.28, -97.74];
        $this->assertNotNull(SellerShipping::localFor($shop->fresh(), $buyer));
        $rider = $this->rider($store);
        $admin = $this->admin();

        Sanctum::actingAs($shop->seller->user);
        $this->patchJson('/api/seller/shipping', ['local_delivery' => null])->assertStatus(422); // needs the popup's confirmation
        $this->patchJson('/api/seller/shipping', ['local_delivery' => null, 'confirm_off' => true])->assertOk()
            ->assertJsonPath('data.local_store.status', 'off_requested');
        $this->assertNotNull(SellerShipping::localFor($shop->fresh(), $buyer), 'stays on until admin decides');
        Notification::assertSentTo($admin, AdminLocalDeliveryOff::class);
        Notification::assertNothingSentTo($rider);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonPath('data.local_delivery_off.0.riders', 1);
        $this->patchJson("/api/admin/stores/{$store->id}/local-delivery", ['action' => 'approve_off'])->assertOk();
        Notification::assertSentTo($rider, RiderNotice::class);
        $this->assertSame(1, $store->riders()->count(), 'riders stay linked — their pay is still due');
        $this->assertNull(SellerShipping::localFor($shop->fresh(), $buyer));
        $this->assertSame('locked', SellerStores::status($store->fresh()));

        // Locked: the seller can't turn it back on; admin can.
        Sanctum::actingAs($shop->seller->user->fresh());
        $this->patchJson('/api/seller/shipping', ['local_delivery' => ['address_id' => $shop->addresses()->value('id'), 'radius_km' => 5, 'fee_cents' => 0, 'days' => 1]])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'switched off local delivery'));
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/stores/{$store->id}/local-delivery", ['action' => 'on'])->assertOk();
        $this->assertSame('on', SellerStores::status($store->fresh()));
        $this->assertNotNull(SellerShipping::localFor($shop->fresh(), $buyer));
    }

    public function test_without_riders_it_turns_off_at_once_and_admin_can_keep_on_or_turn_off(): void
    {
        Notification::fake();
        $shop = $this->shop();
        $store = $this->localOn($shop);
        Sanctum::actingAs($shop->seller->user);
        // No riders: simply off, not locked — the seller can turn it on again.
        $this->patchJson('/api/seller/shipping', ['local_delivery' => null, 'confirm_off' => true])->assertOk()->assertJsonPath('data.local_store.status', 'off');
        $this->patchJson('/api/seller/shipping', ['local_delivery' => ['address_id' => $shop->addresses()->value('id'), 'radius_km' => 5, 'fee_cents' => 0, 'days' => 1, 'lat' => 30.27, 'lng' => -97.74]])
            ->assertOk()->assertJsonPath('data.local_store.status', 'on')->assertJsonPath('data.local_delivery.lat', 30.27);

        // The seller asks off with a rider linked; admin keeps it on with a reason.
        $admin = $this->admin();
        $this->rider($store);
        Sanctum::actingAs($shop->seller->user->fresh());
        $this->patchJson('/api/seller/shipping', ['local_delivery' => null, 'confirm_off' => true])->assertOk()->assertJsonPath('data.local_store.status', 'off_requested');
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/stores/{$store->id}/local-delivery", ['action' => 'keep_on'])->assertStatus(422);
        $this->patchJson("/api/admin/stores/{$store->id}/local-delivery", ['action' => 'keep_on', 'reason' => 'Riders depend on it'])->assertOk();
        $this->assertSame('on', SellerStores::status($store->fresh()));

        // Admin turns it off (reason required): locked.
        $this->patchJson("/api/admin/stores/{$store->id}/local-delivery", ['action' => 'off'])->assertStatus(422);
        $this->patchJson("/api/admin/stores/{$store->id}/local-delivery", ['action' => 'off', 'reason' => 'Late deliveries'])->assertOk();
        $this->assertSame('locked', SellerStores::status($store->fresh()));
    }
}
