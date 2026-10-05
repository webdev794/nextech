<?php

namespace Tests\Feature;

use App\Models\SalesTaxRate;
use App\Models\Setting;
use App\Support\SalesTax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('sales_tax_mode', 'state'); // the test env defaults to flat
    }

    public function test_state_rate_is_used_without_a_lookup_key(): void
    {
        $this->assertSame(725, SalesTax::rateBps('US', 'CA', '90210', 887));
        $this->assertSame(725, SalesTax::rateBps('US', 'California', null, 887));
        $this->assertSame(0, SalesTax::rateBps('US', 'OR', null, 887));
        // Unknown state: the flat rate.
        $this->assertSame(887, SalesTax::rateBps('US', null, null, 887));
    }

    public function test_admin_edits_and_flat_mode(): void
    {
        Setting::put('sales_tax_states', ['CA' => 800]);
        $this->assertSame(800, SalesTax::rateBps('US', 'CA', null, 887));

        Setting::put('sales_tax_mode', 'flat');
        $this->assertSame(887, SalesTax::rateBps('US', 'CA', null, 887));
    }

    public function test_blank_state_uses_the_default_rate(): void
    {
        Setting::put('sales_tax_states', ['CA' => null]);
        $this->assertSame(887, SalesTax::rateBps('US', 'CA', null, 887));
    }

    public function test_fetch_automatically_fills_state_rates(): void
    {
        Setting::put('sales_tax_api_key', 'test-key');
        Http::fake(['*' => Http::response([['zip_code' => '95814', 'total_rate' => '0.087500', 'state_rate' => '0.072500']])]);

        $result = SalesTax::fetchStateRates();

        $this->assertContains('CA', $result['updated']);
        $this->assertSame(725, SalesTax::stateRates()['CA']);
        $this->assertSame(725, SalesTax::stateRates()['TX']); // every sample answered 7.25% here
    }

    public function test_tax_inclusive_markets_add_nothing(): void
    {
        $this->assertSame(0, SalesTax::rateBps('IN', 'MH', '400001', 887));
    }

    public function test_zip_lookup_is_used_and_cached(): void
    {
        Setting::put('sales_tax_api_key', 'test-key');
        Http::fake(['*' => Http::response([['zip_code' => '90210', 'total_rate' => '0.102500']])]);

        $this->assertSame(1025, SalesTax::rateBps('US', 'CA', '90210', 887));
        $this->assertSame(1025, SalesTax::rateBps('US', 'CA', '90210-1234', 887));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sales_tax_rates', ['zip_code' => '90210', 'rate_bps' => 1025]);
    }

    public function test_failed_lookup_falls_back_to_the_state_rate(): void
    {
        Setting::put('sales_tax_api_key', 'test-key');
        Http::fake(['*' => Http::response(['error' => 'quota'], 429)]);

        $this->assertSame(725, SalesTax::rateBps('US', 'CA', '90210', 887));
        $this->assertSame(0, SalesTaxRate::count());
    }
}
