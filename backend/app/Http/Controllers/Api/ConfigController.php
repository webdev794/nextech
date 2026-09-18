<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\HomeTile;
use App\Models\Setting;
use App\Models\Store;
use App\Support\Branding;
use App\Support\CheckoutFees;
use App\Support\FooterConfig;
use App\Support\Payments;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Public client configuration for the web and mobile storefronts.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'currency' => 'usd',
                'stripe_publishable_key' => Payments::stripe()['key'],
                'payments_enabled' => Payments::stripe()['secret'] !== '',
                'branding' => Branding::current(),
                'footer' => FooterConfig::current(),
                'otp_enabled' => (bool) config('otp.enabled'),
                'cod_enabled' => (bool) Setting::get('cod_enabled', false),
                'enforce_radius' => (bool) config('checkout.enforce_radius'),
                ...CheckoutFees::current(),
                'stores' => Store::query()->where('is_active', true)
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
    private function homeTiles()
    {
        $categories = Category::query()->where('is_active', true)
            ->get(['name', 'slug', 'image_url'])->keyBy('slug');

        return HomeTile::query()->active()->ordered()->get()
            ->map(function (HomeTile $tile) use ($categories) {
                $category = $tile->category_slug ? $categories->get($tile->category_slug) : null;

                return [
                    'id' => $tile->id,
                    'title' => $tile->title ?: ($category->name ?? null),
                    'image_url' => $tile->image_url ?: ($category->image_url ?? null),
                    'category_slug' => $category?->slug,
                    'link_url' => $tile->link_url,
                ];
            })
            ->filter(fn (array $tile) => $tile['category_slug'] || $tile['link_url'])
            ->values();
    }
}
