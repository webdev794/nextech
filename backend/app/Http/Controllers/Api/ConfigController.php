<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Store;
use App\Support\Branding;
use App\Support\CheckoutFees;
use App\Support\Country;
use App\Support\FooterConfig;
use App\Support\Market;
use App\Support\Payments;
use App\Support\SellerLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * Public client configuration for the web and mobile storefronts.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $market = Market::fromRequest($request);

        return response()->json([
            'data' => [
                'market' => $market,
                'currency' => Market::currency($market),
                // Every market shoppers can switch to, each with its own fees.
                'markets' => collect(Market::publicList())->map(fn (array $m) => $m + ['fees' => CheckoutFees::current($m['code'])])->values(),
                // India: the grievance officer every e-commerce entity must
                // publish (Consumer Protection (E-Commerce) Rules, 2020, rule 4(4)).
                'grievance_officer' => Setting::get('grievance_officer'),
                'active_countries' => Country::active(),
                'stripe_publishable_key' => Payments::stripe()['key'],
                'payments_enabled' => Payments::stripe()['secret'] !== '',
                'branding' => Branding::current(),
                'footer' => FooterConfig::current(),
                'otp_enabled' => (bool) config('otp.enabled'),
                'cod_enabled' => (bool) Setting::get('cod_enabled', false),
                'enforce_radius' => (bool) config('checkout.enforce_radius'),
                // Read-only and not sensitive — a seller can already back this
                // out from their own ledger entries, so surfacing it directly
                // lets the Seller Center price estimator use the real rate.
                'commission_rate_bps' => SellerLedger::rate($market),
                'return_window_days' => SellerLedger::returnWindowDays(),
                'max_return_days' => SellerLedger::maxReturnDays(),
                ...CheckoutFees::current($market),
                'stores' => Store::query()->where('country', $market)->where('is_active', true)
                    ->whereNotNull('latitude')->whereNotNull('longitude')
                    ->get(['id', 'name', 'latitude', 'longitude', 'delivery_radius_km']),
                'banners' => Banner::query()->active()->ordered()
                    ->get(['id', 'image_url', 'headline', 'category_slug', 'link_url', 'placement']),
                'home_tiles' => $this->homeTiles(),
            ],
        ]);
    }

    /**
     * Curated homepage tiles with title/image resolved against the linked
     * category. Tiles that resolve to no destination are dropped.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    /**
     * The homepage category carousel: every active category ticked "Show on
     * homepage", in the categories' own sort order. (Replaces the separate
     * admin-managed home_tiles list, which only ever mirrored categories.)
     */
    private function homeTiles()
    {
        return Category::query()
            ->where('is_active', true)
            ->where('show_on_home', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image_url'])
            ->map(fn (Category $category) => [
                'id' => 'cat-'.$category->id,
                'title' => $category->name,
                'image_url' => $category->image_url,
                'category_slug' => $category->slug,
                'link_url' => null,
            ])
            ->values();
    }
}
