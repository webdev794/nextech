<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\SellerLedgerEntry;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Support\SellerOnboarding;
use App\Support\SellerPayouts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerStripePayoutsTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $market = 'US'): Shop
    {
        $seller = Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => $market, 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => $market,
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);

        return Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme-'.strtolower($market), 'is_active' => true, 'fulfillment_mode' => 'self', 'market' => $market]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /** Stripe keys set, and the store's Stripe account is in the US (no call to Stripe). */
    private function fakeStripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);
        Cache::put('stripe_platform_country:'.substr(hash('sha256', 'sk_test_fake'), 0, 12), 'US', now()->addHour());
    }

    public function test_stripe_is_off_by_default_and_only_offered_in_the_stripe_accounts_country(): void
    {
        config(['services.stripe.secret' => null]);
        $this->assertFalse(SellerPayouts::fees('US')['stripe']['enabled']);
        $this->assertStringContainsString('Stripe keys', SellerPayouts::fees('US')['stripe']['unavailable']);

        $this->fakeStripe();
        $this->assertNull(SellerPayouts::fees('US')['stripe']['unavailable']);
        $this->assertNotNull(SellerPayouts::fees('IN')['stripe']['unavailable']);

        // Even switched on in settings, India can't offer it.
        Setting::put('payout_fees', ['IN' => ['stripe' => ['enabled' => true]]]);
        $this->assertFalse(SellerPayouts::fees('IN')['stripe']['enabled']);
    }

    public function test_payout_settings_need_secure_access_and_stripe_can_only_be_switched_on_where_it_works(): void
    {
        $this->fakeStripe();
        Sanctum::actingAs($this->admin());
        $body = ['payout_fees' => ['IN' => ['bank' => ['enabled' => true], 'stripe' => ['enabled' => true]]]];

        $this->patchJson('/api/admin/settings', $body)->assertForbidden();

        $token = $this->postJson('/api/admin/secure-access/unlock', ['password' => 'password'])->assertOk()->json('data.token');
        $this->withHeader('X-Secure-Access', $token)->patchJson('/api/admin/settings', $body)
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Stripe'));

        $this->withHeader('X-Secure-Access', $token)->patchJson('/api/admin/settings', ['payout_fees' => ['US' => ['bank' => ['enabled' => true], 'stripe' => ['enabled' => true, 'fixed_cents' => 50, 'bps' => 100]]]])->assertOk();
        $this->assertTrue(SellerPayouts::fees('US')['stripe']['enabled']);
        $this->assertSame(50 + 100, SellerPayouts::fee('US', 'stripe', 10000));

        // Still unlocked later in the same login.
        $this->withHeader('X-Secure-Access', $token)->getJson('/api/admin/secure-access/state')->assertOk();
    }

    public function test_seller_needs_a_ready_stripe_account_and_that_completes_the_payout_task(): void
    {
        $this->fakeStripe();
        Setting::put('payout_fees', ['US' => ['stripe' => ['enabled' => true]]]);
        $shop = $this->shop();
        $seller = $shop->seller;
        Sanctum::actingAs($seller->user);

        $this->patchJson('/api/seller/payout-method', ['method' => 'stripe'])->assertStatus(422);
        $this->assertSame('todo', SellerOnboarding::tasks($seller->fresh())['bank'] ?? 'todo');

        $seller->forceFill(['stripe_account_id' => 'acct_test', 'stripe_ready' => true])->save();
        Sanctum::actingAs($seller->user->fresh());
        $this->patchJson('/api/seller/payout-method', ['method' => 'stripe'])->assertOk()->assertJsonPath('data.payout_method', 'stripe')->assertJsonPath('data.payout_blocker', null);
        $this->assertSame('linked', SellerOnboarding::tasks($seller->fresh())['bank']);
    }

    public function test_payouts_table_and_paying_sit_behind_secure_access(): void
    {
        $shop = $this->shop();
        $shop->seller->forceFill(['payout_method' => 'paypal', 'payout_details' => ['paypal_email' => 'pat@example.com']])->save();
        Setting::put('payout_fees', ['US' => ['paypal' => ['enabled' => true, 'fixed_cents' => 100]]]);
        SellerLedgerEntry::create(['shop_id' => $shop->id, 'order_id' => null, 'type' => 'adjustment', 'amount_cents' => 200000, 'note' => 'credit']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/secure-access/payouts')->assertForbidden();
        $this->postJson("/api/admin/sellers/{$shop->seller_id}/payout", ['amount_cents' => 1000])->assertForbidden();

        $token = $this->postJson('/api/admin/secure-access/unlock', ['password' => 'password'])->json('data.token');
        $row = $this->withHeader('X-Secure-Access', $token)->getJson('/api/admin/secure-access/payouts')->assertOk()->json('data.0');
        $this->assertSame('PayPal', $row['method_label']);
        $this->assertSame('pat@example.com', $row['method_detail']);
        $this->assertSame(100, $row['fee_cents']);
        $this->assertSame($row['amount_cents'] - 100, $row['net_cents']);

        $this->withHeader('X-Secure-Access', $token)->postJson("/api/admin/sellers/{$shop->seller_id}/payout", ['amount_cents' => 10000])->assertOk();
        $this->assertDatabaseHas('seller_ledger_entries', ['shop_id' => $shop->id, 'type' => 'payout_fee', 'amount_cents' => -100]);
    }
}
