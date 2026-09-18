<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_exposes_client_settings(): void
    {
        config([
            'services.stripe.key' => 'pk_test_abc',
            'services.stripe.secret' => 'sk_test_abc',
            'checkout.delivery_fee_cents' => 599,
        ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.stripe_publishable_key', 'pk_test_abc')
            ->assertJsonPath('data.payments_enabled', true)
            ->assertJsonPath('data.delivery_fee_cents', 599)
            ->assertJsonPath('data.currency', 'usd');
    }

    public function test_config_never_leaks_the_secret_key(): void
    {
        config(['services.stripe.secret' => 'sk_test_shhh']);

        $body = $this->getJson('/api/config')->assertOk()->getContent();

        $this->assertStringNotContainsString('sk_test_shhh', $body);
    }
}
