<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Geo caches results; keep tests independent
    }

    public function test_search_maps_nominatim_results_to_the_app_address_shape(): void
    {
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([[
            'lat' => '30.7215',
            'lon' => '76.7350',
            'display_name' => '89, Shiv Mandir Road, Kajheri, Sector 52, Chandigarh, 160036, India',
            'address' => [
                'house_number' => '89', 'road' => 'Shiv Mandir Road', 'suburb' => 'Kajheri',
                'city' => 'Chandigarh', 'state' => 'Chandigarh', 'postcode' => '160036',
                'ISO3166-2-lvl4' => 'IN-CH',
            ],
        ]])]);

        $this->getJson('/api/geocode/search?q=89, Shiv Mandir Road, Chandigarh')
            ->assertOk()
            ->assertJsonPath('data.0.line1', '89 Shiv Mandir Road')
            ->assertJsonPath('data.0.city', 'Chandigarh')
            ->assertJsonPath('data.0.state', 'Chandigarh')
            ->assertJsonPath('data.0.postal_code', '160036')
            ->assertJsonPath('data.0.lat', 30.7215);
    }

    public function test_search_restricts_results_to_a_box_around_the_active_store(): void
    {
        $this->chandigarhStore();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->getJson('/api/geocode/search?q=Shiv Mandir Road')->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'viewbox=')
            && str_contains($request->url(), 'bounded=1'));
    }

    public function test_search_drops_a_result_far_from_the_store(): void
    {
        $this->chandigarhStore();
        // A "Shiv Mandir" ~100 km away in Ludhiana — outside the box and the cap.
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '30.9083581', 'lon' => '75.8864411',
            'display_name' => 'Shiri Shiv Mandir, Ludhiana, Punjab, India',
            'address' => ['city' => 'Ludhiana', 'state' => 'Punjab'],
        ]])]);

        $this->getJson('/api/geocode/search?q=Shiv Mandir Road')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_search_retries_without_the_trailing_segment_when_empty(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([])
            ->push([['lat' => '30.73', 'lon' => '76.77', 'display_name' => 'Chandigarh, India', 'address' => ['city' => 'Chandigarh']]]),
        ]);

        $this->getJson('/api/geocode/search?q=Shiv Mandir Road, Chandigarh, CH')
            ->assertOk()
            ->assertJsonPath('data.0.city', 'Chandigarh');

        Http::assertSentCount(2);
    }

    public function test_reverse_maps_a_point(): void
    {
        Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response([
            'lat' => '40.7484', 'lon' => '-73.9857',
            'display_name' => '20 W 34th St, New York, NY 10001, USA',
            'address' => ['house_number' => '20', 'road' => 'West 34th Street', 'city' => 'New York', 'state' => 'New York', 'postcode' => '10001', 'ISO3166-2-lvl4' => 'US-NY'],
        ])]);

        $this->getJson('/api/geocode/reverse?lat=40.7484&lng=-73.9857')
            ->assertOk()
            ->assertJsonPath('data.line1', '20 West 34th Street')
            ->assertJsonPath('data.state', 'New York')
            ->assertJsonPath('data.city', 'New York');
    }

    public function test_a_specific_far_address_is_still_returned(): void
    {
        $this->chandigarhStore(); // ~230 km from Dehradun, well past the cap
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '30.3165', 'lon' => '78.0322',
            'display_name' => '18, Chowk, Paltan Bazaar, Dehradun, Uttarakhand, 248001, India',
            'address' => ['road' => 'Chowk', 'city' => 'Dehradun', 'state' => 'Uttarakhand', 'postcode' => '248001'],
        ]])]);

        $this->getJson('/api/geocode/search?q='.urlencode('18, Chowk, Paltan Bazaar, Dehradun, Uttarakhand 248001'))
            ->assertOk()
            ->assertJsonPath('data.0.city', 'Dehradun');

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'bounded=1'));
    }

    public function test_search_requires_a_query_of_at_least_three_characters(): void
    {
        $this->getJson('/api/geocode/search?q=ab')->assertStatus(422);
    }

    public function test_search_biases_to_the_store_nearest_the_map_position(): void
    {
        // Two stores in different cities.
        $this->chandigarhStore(); // 30.7333, 76.7794 — created first
        Store::create([
            'name' => 'Delhi', 'line1' => '2 Store St', 'city' => 'Delhi', 'state' => 'DL',
            'postal_code' => '110001', 'latitude' => 28.6139, 'longitude' => 77.2090,
            'delivery_radius_km' => 8, 'is_active' => true,
        ]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        // Map is looking at Delhi -> viewbox is built around Delhi's store
        // (77.2090 - 0.6 = 76.6090), not the first store in Chandigarh.
        $this->getJson('/api/geocode/search?q=Main Road&lat=28.62&lng=77.21')->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'viewbox=76.609'));
    }

    public function test_search_falls_back_to_the_first_store_without_a_map_position(): void
    {
        $this->chandigarhStore(); // 76.7794 - 0.6 = 76.1794
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->getJson('/api/geocode/search?q=Main Road')->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'viewbox=76.179'));
    }

    private function chandigarhStore(): Store
    {
        return Store::create([
            'name' => 'Chandigarh', 'line1' => '1 Store St', 'city' => 'Chandigarh', 'state' => 'CH',
            'postal_code' => '160017', 'latitude' => 30.7333, 'longitude' => 76.7794,
            'delivery_radius_km' => 8, 'is_active' => true,
        ]);
    }
}
