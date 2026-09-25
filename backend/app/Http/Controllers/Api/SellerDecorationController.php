<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\StoreDecoration;
use App\Support\StoreDecorations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Seller Center -> My account -> Store decoration: versions of the store
 * page for desktop and mobile. A version is edited as a draft, submitted
 * (checked for missing content, and spot-checked by NexTech now and then),
 * and published once approved — one live version per platform.
 */
class SellerDecorationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $products = StoreDecorations::liveProducts($shop);

        return response()->json(['data' => [
            'terms_accepted_at' => $shop->decoration_terms_accepted_at,
            'versions' => $shop->decorations()->latest('updated_at')->get(),
            'shop' => $shop->only(['name', 'slug', 'logo_url', 'banner_url']),
            'live_products' => $products->count(),
            'min_products' => StoreDecorations::minProducts(),
            'max_versions' => StoreDecorations::MAX_VERSIONS,
            'image_specs' => StoreDecorations::imageSpecs(),
            'products' => $products->map->only(['id', 'name', 'slug', 'image_url', 'price_cents', 'compare_at_price_cents', 'category_id'])->values(),
            'categories' => \App\Models\Category::whereIn('id', StoreDecorations::shopCategoryIds($shop))->orderBy('name')->get(['id', 'name', 'slug', 'image_url']),
        ]]);
    }

    /** The Data Processing Agreement, accepted once before decorating. */
    public function acceptTerms(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $shop->forceFill(['decoration_terms_accepted_at' => now()])->save();

        return response()->json(['data' => ['terms_accepted_at' => $shop->decoration_terms_accepted_at]]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $this->assertTerms($shop);
        $data = $request->validate([
            'platform' => ['required', Rule::in(StoreDecorations::PLATFORMS)],
            'name' => ['nullable', 'string', 'max:80'],
            'copy_from' => ['nullable', 'integer'],
        ]);
        abort_if($shop->decorations()->where('platform', $data['platform'])->count() >= StoreDecorations::MAX_VERSIONS, 422, 'You can keep up to '.StoreDecorations::MAX_VERSIONS.' versions per platform — delete one first.');

        $source = ! empty($data['copy_from']) ? $shop->decorations()->findOrFail($data['copy_from']) : null;
        $count = $shop->decorations()->where('platform', $data['platform'])->count();
        $decoration = $shop->decorations()->create([
            'platform' => $data['platform'],
            'name' => trim((string) ($data['name'] ?? '')) ?: ($source ? $source->name.' (copy)' : ucfirst($data['platform']).' version '.($count + 1)),
            'status' => 'draft',
            'page' => $source?->page ?? StoreDecorations::cleanPage(null),
            'sections' => $source?->sections ?? [],
        ]);

        return response()->json(['data' => $decoration], 201);
    }

    public function update(Request $request, StoreDecoration $decoration): JsonResponse
    {
        $shop = $this->shop($request);
        $this->own($shop, $decoration);
        abort_if($decoration->is_live, 422, 'This version is live — make a copy to change it.');
        abort_if($decoration->status === 'in_review', 422, 'This version is being checked by NexTech — wait for the result or make a copy.');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'page' => ['sometimes', 'array'],
            'sections' => ['sometimes', 'array', 'max:'.StoreDecorations::MAX_SECTIONS],
        ]);

        $decoration->update([
            'status' => 'draft', // any change needs submitting again
            'review_note' => null,
        ] + (isset($data['name']) ? ['name' => trim($data['name'])] : [])
          + (isset($data['page']) ? ['page' => StoreDecorations::cleanPage($data['page'])] : [])
          + (isset($data['sections']) ? ['sections' => StoreDecorations::clean($data['sections'])] : []));

        return response()->json(['data' => $decoration->fresh()]);
    }

    /**
     * Check for missing content, then either approve straight away or pick it
     * for a NexTech spot check.
     */
    public function submit(Request $request, StoreDecoration $decoration): JsonResponse
    {
        $shop = $this->shop($request);
        $this->own($shop, $decoration);
        abort_unless($decoration->status === 'draft' || $decoration->status === 'rejected', 422, 'Only drafts can be submitted.');
        $problems = StoreDecorations::problems($decoration, $shop);
        if ($problems) {
            return response()->json(['message' => 'Fix these before submitting.', 'problems' => $problems], 422);
        }

        $spotCheck = mt_rand() / mt_getrandmax() < StoreDecorations::spotCheckRate();
        $decoration->update([
            'status' => $spotCheck ? 'in_review' : 'approved',
            'submitted_at' => now(),
            'reviewed_at' => $spotCheck ? null : now(),
            'review_note' => null,
        ]);

        return response()->json(['data' => $decoration->fresh()]);
    }

    public function publish(Request $request, StoreDecoration $decoration): JsonResponse
    {
        $shop = $this->shop($request);
        $this->own($shop, $decoration);
        abort_unless($decoration->status === 'approved', 422, 'Only approved versions can be published.');

        DB::transaction(function () use ($shop, $decoration) {
            $shop->decorations()->where('platform', $decoration->platform)->where('is_live', true)->update(['is_live' => false]);
            $decoration->update(['is_live' => true, 'published_at' => now()]);
        });

        return response()->json(['data' => $decoration->fresh()]);
    }

    /** Go back to the default store page on this platform. */
    public function unpublish(Request $request, StoreDecoration $decoration): JsonResponse
    {
        $shop = $this->shop($request);
        $this->own($shop, $decoration);
        $decoration->update(['is_live' => false]);

        return response()->json(['data' => $decoration->fresh()]);
    }

    public function destroy(Request $request, StoreDecoration $decoration): JsonResponse
    {
        $shop = $this->shop($request);
        $this->own($shop, $decoration);
        abort_if($decoration->is_live, 422, 'Unpublish this version before deleting it.');
        $decoration->delete();

        return response()->json(status: 204);
    }

    private function assertTerms(Shop $shop): void
    {
        abort_unless($shop->decoration_terms_accepted_at, 422, 'Agree to the Data Processing Agreement first.');
    }

    private function own(Shop $shop, StoreDecoration $decoration): void
    {
        abort_unless($decoration->shop_id === $shop->id, 404);
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');

        return $seller->shop;
    }
}
