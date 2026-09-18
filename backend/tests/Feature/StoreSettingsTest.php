<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\SendOtpCode;
use App\Support\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /** Unlock Secure access with the admin password (default mode) and return the token. */
    private function unlockSecure(string $password = 'password'): string
    {
        return $this->postJson('/api/admin/secure-access/unlock', ['password' => $password])
            ->assertOk()->json('data.token');
    }

    public function test_branding_defaults_are_exposed_and_editable(): void
    {
        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.branding.store_name', 'NexTech')
            ->assertJsonPath('data.branding.theme', 'light')
            ->assertJsonPath('data.branding.layout_width', 'boxed')
            ->assertJsonPath('data.branding.color_brand', '#1f7a3d');

        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/admin/settings', [
            'store_name' => 'FreshCart',
            'theme' => 'dark',
            'layout_width' => 'full',
            'color_brand' => '#0055ff',
            'logo_url' => 'https://cdn.example/logo.png',
        ])->assertOk()
            ->assertJsonPath('data.branding.store_name', 'FreshCart')
            ->assertJsonPath('data.branding.theme', 'dark')
            ->assertJsonPath('data.branding.layout_width', 'full')
            ->assertJsonPath('data.branding.color_brand', '#0055ff');

        $this->getJson('/api/config')
            ->assertJsonPath('data.branding.store_name', 'FreshCart')
            ->assertJsonPath('data.branding.logo_url', 'https://cdn.example/logo.png');
    }

    public function test_a_bad_colour_is_rejected(): void
    {
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/admin/settings', ['color_accent' => 'red'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color_accent']);
    }

    public function test_changing_payment_settings_needs_the_unlock_token(): void
    {
        Sanctum::actingAs($this->admin());

        // No unlock token -> refused.
        $this->patchJson('/api/admin/settings', ['stripe_key' => 'pk_live_abc123'])
            ->assertForbidden();

        // A non-payment setting still works without unlocking.
        $this->patchJson('/api/admin/settings', ['store_name' => 'FreshCart'])->assertOk();
    }

    public function test_password_unlock_then_save_stripe_keys(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/settings')->assertJsonPath('data.secure_access.method', 'password');
        $this->postJson('/api/admin/secure-access/unlock', ['password' => 'wrong-one'])->assertStatus(422);

        $token = $this->unlockSecure();

        $this->withHeader('X-Secure-Access', $token)
            ->patchJson('/api/admin/settings', [
                'stripe_key' => 'pk_live_abc123',
                'stripe_secret' => 'sk_live_supersecretvalue9999',
                'stripe_webhook_secret' => 'whsec_hook_secret_value',
            ])->assertOk()
            ->assertJsonPath('data.payments.stripe_key', 'pk_live_abc123')
            ->assertJsonPath('data.payments.stripe_mode', 'live')
            ->assertJsonPath('data.payments.stripe_secret_set', true);

        $hint = $this->getJson('/api/admin/settings')->json('data.payments.stripe_secret_hint');
        $this->assertStringNotContainsString('supersecret', $hint);
        $this->assertStringStartsWith('sk_live', $hint);
        $this->assertSame('sk_live_supersecretvalue9999', Payments::stripe()['secret']);
    }

    public function test_otp_mode_still_works(): void
    {
        config(['secure_access.method' => 'otp', 'otp.bypass_code' => '424242']);
        Notification::fake();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/secure-access/challenge')
            ->assertStatus(202)
            ->assertJsonPath('data.method', 'otp');
        Notification::assertSentOnDemand(SendOtpCode::class);

        $token = $this->postJson('/api/admin/secure-access/unlock', ['code' => '424242'])
            ->assertOk()->json('data.token');

        $this->withHeader('X-Secure-Access', $token)
            ->patchJson('/api/admin/settings', ['stripe_key' => 'pk_test_otp'])
            ->assertOk()->assertJsonPath('data.payments.stripe_key', 'pk_test_otp');
    }

    public function test_admin_can_change_their_email_and_phone_after_unlocking(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $token = $this->unlockSecure();

        // Refused without the token.
        $this->patchJson('/api/admin/secure-access/account', ['email' => 'new@shop.test'])
            ->assertForbidden();

        $this->withHeader('X-Secure-Access', $token)
            ->patchJson('/api/admin/secure-access/account', ['email' => 'owner@shop.test', 'phone' => '+15551239876'])
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@shop.test')
            ->assertJsonPath('data.phone', '+15551239876');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'email' => 'owner@shop.test', 'phone' => '+15551239876']);
    }

    public function test_email_change_rejects_a_duplicate(): void
    {
        User::factory()->create(['email' => 'taken@shop.test']);
        Sanctum::actingAs($this->admin());
        $token = $this->unlockSecure();

        $this->withHeader('X-Secure-Access', $token)
            ->patchJson('/api/admin/secure-access/account', ['email' => 'taken@shop.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_a_blank_secret_does_not_wipe_the_stored_one(): void
    {
        Setting::put('payments', ['stripe_secret' => 'sk_test_keep_me']);
        Sanctum::actingAs($this->admin());
        $token = $this->unlockSecure();

        $this->withHeader('X-Secure-Access', $token)
            ->patchJson('/api/admin/settings', ['stripe_key' => 'pk_test_new', 'stripe_secret' => ''])
            ->assertOk()
            ->assertJsonPath('data.payments.stripe_secret_set', true);

        $this->assertSame('sk_test_keep_me', Payments::stripe()['secret']);
    }

    public function test_footer_content_is_exposed_and_editable(): void
    {
        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.footer.socials.facebook', '')
            ->assertJsonStructure(['data' => ['footer' => ['copyright', 'note', 'app_store_url', 'play_store_url', 'socials', 'links']]]);

        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/admin/settings', [
            'footer' => [
                'copyright' => '© {year} FreshCart',
                'app_store_url' => 'https://apps.apple.com/app/id123',
                'socials' => ['facebook' => 'https://facebook.com/freshcart', 'x' => '', 'instagram' => 'https://instagram.com/freshcart'],
                'links' => [
                    ['label' => 'Careers', 'url' => 'https://freshcart.example/careers'],
                    ['label' => '', 'url' => ''], // dropped
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.footer.copyright', '© {year} FreshCart')
            ->assertJsonPath('data.footer.socials.facebook', 'https://facebook.com/freshcart')
            ->assertJsonPath('data.footer.socials.instagram', 'https://instagram.com/freshcart')
            ->assertJsonCount(1, 'data.footer.links')
            ->assertJsonPath('data.footer.links.0.label', 'Careers');

        $this->getJson('/api/config')
            ->assertJsonPath('data.footer.copyright', '© {year} FreshCart')
            ->assertJsonPath('data.footer.app_store_url', 'https://apps.apple.com/app/id123');
    }

    public function test_non_admin_cannot_read_settings(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/admin/settings')->assertForbidden();
    }
}
