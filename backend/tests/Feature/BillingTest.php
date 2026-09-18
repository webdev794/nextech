<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stripe is not configured in the test environment, so these cover the auth
 * guards and the graceful "not configured" responses. Live card flows are
 * exercised against Stripe test mode by hand.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_endpoints_require_authentication(): void
    {
        $this->getJson('/api/billing/payment-methods')->assertUnauthorized();
        $this->postJson('/api/billing/setup-intent')->assertUnauthorized();
        $this->deleteJson('/api/billing/payment-methods/pm_123')->assertUnauthorized();
    }

    public function test_payment_methods_are_empty_when_stripe_is_not_configured(): void
    {
        config(['services.stripe.secret' => '']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing/payment-methods')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('enabled', false);
    }

    public function test_setup_intent_is_unavailable_without_stripe(): void
    {
        config(['services.stripe.secret' => '']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/billing/setup-intent')->assertStatus(503);
    }

    public function test_a_new_customer_has_no_stripe_customer_id_yet(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->stripe_customer_id);
    }
}
