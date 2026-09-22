<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SellerController extends Controller
{
    /**
     * Single-submit seller registration: the frontend wizard holds all 4
     * steps' state client-side and submits once, mirroring
     * AuthController::register()'s transaction style.
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->seller !== null, 409, 'You already have a seller application.');

        $countryCodes = array_keys(config('countries', []));

        $data = $request->validate([
            'country' => ['required', 'string', Rule::in($countryCodes)],
            'business_type' => ['required', 'string', Rule::in(['individual', 'proprietorship', 'private_limited', 'state_owned', 'public_listed'])],
            'company_name' => ['required', 'string', 'max:160'],
            'tax_id' => ['required', 'string', 'max:60'],

            'registered_line1' => ['required', 'string', 'max:255'],
            'registered_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registered_city' => ['required', 'string', 'max:100'],
            'registered_state' => ['required', 'string', 'max:60'],
            'registered_postal_code' => ['required', 'string', 'max:12'],
            'registered_country' => ['required', 'string', Rule::in($countryCodes)],

            'contact_name' => ['required', 'string', 'max:160'],
            'id_type' => ['required', 'string', 'max:32'],
            'id_number' => ['required', 'string', 'max:60'],
            'date_of_birth' => ['required', 'date'],
            'id_document_path' => ['required', 'string', 'max:255'],
            'business_document_path' => ['required', 'string', 'max:255'],

            'shop_name' => ['required', 'string', 'max:160'],
            'shop_logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'shop_banner_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'shop_category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'shop_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $seller = DB::transaction(function () use ($user, $data): Seller {
            $seller = Seller::create([
                'user_id' => $user->id,
                'country' => strtoupper($data['country']),
                'business_type' => $data['business_type'],
                'company_name' => $data['company_name'],
                'tax_id' => $data['tax_id'],
                'registered_line1' => $data['registered_line1'],
                'registered_line2' => $data['registered_line2'] ?? null,
                'registered_city' => $data['registered_city'],
                'registered_state' => $data['registered_state'],
                'registered_postal_code' => $data['registered_postal_code'],
                'registered_country' => strtoupper($data['registered_country']),
                'contact_name' => $data['contact_name'],
                'id_type' => $data['id_type'],
                'id_number' => $data['id_number'],
                'date_of_birth' => $data['date_of_birth'],
                'id_document_path' => $data['id_document_path'],
                'business_document_path' => $data['business_document_path'],
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            $seller->shop()->create([
                'name' => $data['shop_name'],
                'slug' => $this->uniqueSlug($data['shop_name']),
                'logo_url' => $data['shop_logo_url'] ?? null,
                'banner_url' => $data['shop_banner_url'] ?? null,
                'category_id' => $data['shop_category_id'] ?? null,
                'description' => $data['shop_description'] ?? null,
                'is_active' => false,
            ]);

            return $seller;
        });

        return response()->json(['data' => $seller->load('shop')], 201);
    }

    /**
     * Null (=> show the wizard) or the seller+shop+status (=> status page or
     * dashboard). Gated only by auth:sanctum, not `seller`, since a
     * first-time applicant has no seller record yet.
     */
    public function me(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->with('shop')->first();

        if ($seller?->shop) {
            $seller->balance_cents = $seller->shop->balanceCents();
            $seller->ledger_entries = $seller->shop->ledgerEntries()
                ->latest()
                ->limit(20)
                ->get(['id', 'shop_id', 'order_id', 'type', 'amount_cents', 'commission_cents', 'note', 'created_at']);
        }

        return response()->json(['data' => $seller]);
    }

    /**
     * Edit shop name/logo/description post-approval. `seller`-gated (has an
     * application at all — pending/rejected/suspended sellers can still see
     * their own shop, they just can't publish it since is_active stays false).
     */
    public function updateShop(Request $request): JsonResponse
    {
        $shop = $request->user()->seller->shop;
        abort_unless($shop, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'banner_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $shop->update($data);

        return response()->json(['data' => $shop->fresh()]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $suffix = 2;

        while (Shop::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
