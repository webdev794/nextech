<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\Seller;
use App\Models\Shop;
use App\Support\Market;
use App\Support\Money;
use App\Support\SellerLedger;
use App\Support\SellerPayouts;
use App\Support\SellerOnboarding;
use App\Support\SellerRequirements;
use App\Support\Sku;
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
        $existing = $user->seller;

        // A first-time applicant creates a new row; a seller admin sent back
        // to 'needs_changes' resubmits against the same row instead — anyone
        // else (pending/approved/rejected/suspended) already has a decision
        // in flight and can't re-apply.
        abort_if($existing !== null && $existing->status !== 'needs_changes', 409, 'You already have a seller application.');

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

            'pickup_same_as_registered' => ['sometimes', 'boolean'],
            'pickup_phone' => ['required', 'string', 'max:32'],
            'pickup_line1' => ['required_if:pickup_same_as_registered,false', 'nullable', 'string', 'max:255'],
            'pickup_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pickup_city' => ['required_if:pickup_same_as_registered,false', 'nullable', 'string', 'max:100'],
            'pickup_state' => ['required_if:pickup_same_as_registered,false', 'nullable', 'string', 'max:60'],
            'pickup_postal_code' => ['required_if:pickup_same_as_registered,false', 'nullable', 'string', 'max:12'],
            'pickup_country' => ['required_if:pickup_same_as_registered,false', 'nullable', 'string', Rule::in($countryCodes)],

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

        $sameAsRegistered = (bool) ($data['pickup_same_as_registered'] ?? true);

        $seller = DB::transaction(function () use ($user, $data, $sameAsRegistered, $existing): Seller {
            $attributes = [
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
                'pickup_same_as_registered' => $sameAsRegistered,
                'pickup_phone' => $data['pickup_phone'],
                'pickup_line1' => $sameAsRegistered ? $data['registered_line1'] : $data['pickup_line1'],
                'pickup_line2' => $sameAsRegistered ? ($data['registered_line2'] ?? null) : ($data['pickup_line2'] ?? null),
                'pickup_city' => $sameAsRegistered ? $data['registered_city'] : $data['pickup_city'],
                'pickup_state' => $sameAsRegistered ? $data['registered_state'] : $data['pickup_state'],
                'pickup_postal_code' => $sameAsRegistered ? $data['registered_postal_code'] : $data['pickup_postal_code'],
                'pickup_country' => $sameAsRegistered ? strtoupper($data['registered_country']) : strtoupper($data['pickup_country']),
                'contact_name' => $data['contact_name'],
                'id_type' => $data['id_type'],
                'id_number' => $data['id_number'],
                'date_of_birth' => $data['date_of_birth'],
                'id_document_path' => $data['id_document_path'],
                'business_document_path' => $data['business_document_path'],
                'status' => 'pending',
                'submitted_at' => now(),
            ];

            if ($existing) {
                // Resubmission after 'needs_changes' — back into the review
                // queue clean, with no stale reviewer/reason from last time.
                $existing->forceFill($attributes + [
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                    'change_items' => null,
                ])->save();
                $seller = $existing;
            } else {
                $seller = Seller::create(['user_id' => $user->id] + $attributes);
            }

            $shopAttributes = [
                'logo_url' => $data['shop_logo_url'] ?? null,
                'banner_url' => $data['shop_banner_url'] ?? null,
                'category_id' => $data['shop_category_id'] ?? null,
                'description' => $data['shop_description'] ?? null,
            ];

            if ($seller->shop) {
                // Slug stays put on a resubmission — the shop may already be
                // linked to elsewhere (or just be familiar to the seller).
                $seller->shop->update(['name' => $data['shop_name']] + $shopAttributes);
            } else {
                $seller->shop()->create([
                    'name' => $data['shop_name'],
                    'slug' => $this->uniqueSlug($data['shop_name']),
                    'shop_code' => Sku::assignShopCode($data['shop_name']),
                    'is_active' => false,
                ] + $shopAttributes);
            }

            return $seller;
        });

        return response()->json(['data' => $seller->load('shop')], $existing ? 200 : 201);
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
            // Earnings are held until the order's return window has passed.
            $split = SellerLedger::breakdown($seller->shop);
            $seller->available_cents = $split['available_cents'];
            $seller->pending_cents = $split['pending_cents'];
            $seller->pending_orders = $split['pending'];
            $seller->return_window_days = SellerLedger::returnWindowDays();
            $seller->ledger_entries = $seller->shop->ledgerEntries()
                ->latest()
                ->limit(20)
                ->get(['id', 'shop_id', 'order_id', 'type', 'amount_cents', 'commission_cents', 'note', 'created_at']);
            // Whether payout details are relevant yet — a seller who's been
            // fully paid out (balance back to 0) still needs their method on
            // file, so this checks ledger history, not the current balance.
            $seller->has_sales = $seller->shop->ledgerEntries()->exists();
            $seller->min_payout_cents = SellerLedger::minPayoutCents($seller->shop->market);
            $seller->max_payout_cents = SellerLedger::maxPayoutCents($seller->shop->market);
            // Daily cap on all payouts sent (a big balance may be paid over several days) and the bank-rules note.
            $seller->daily_payout_cap_cents = SellerLedger::dailyPayoutCapCents($seller->shop->market);
            $seller->payout_note = SellerLedger::payoutNote($seller->shop->market);
            // Payout fees per method (fixed + %), and whether this seller can be paid right now.
            $seller->payout_fees = SellerPayouts::fees($seller->shop->market);
            // Rate from the shop's currency to each method's payout currency (e.g. INR → USD for PayPal).
            $seller->payout_rates = SellerPayouts::rates($seller->shop->market);
            $seller->payout_currency = SellerPayouts::currencyFor($seller, $seller->payout_method === 'paypal' ? 'paypal' : 'bank');
            $seller->payout_blocker = SellerPayouts::blocker($seller);
            $seller->last_payout_request = $seller->shop->payoutRequests()->latest('id')->first();
        }

        // The market the seller sells in: its currency, and whether prices must
        // include GST (India) with HSN / GST rate / origin / manufacturer details.
        if ($seller) {
            $market = $seller->shop?->market ?? Market::forCountry($seller->country);
            $seller->market = $market;
            $seller->currency = Market::currency($market);
            $seller->tax_inclusive = Market::taxInclusive($market);
            $seller->gst_rates_bps = Market::profile($market)['gst_rates_bps'] ?? [];
            $seller->withholding = collect(Market::profile($market)['withholding'] ?? [])->map(fn ($r) => $r['label'].' '.($r['rate_bps'] / 100).'%')->values();
            $seller->onboarding_tasks = SellerOnboarding::tasks($seller);
            $seller->requirements = SellerRequirements::all($seller->shop);
        }

        return response()->json(['data' => $seller]);
    }

    /**
     * Ask admin to be paid out. One open request at a time; the amount is the
     * current balance capped at the per-transfer maximum (a larger balance is
     * paid over several requests). Admin sees it in the notification bell.
     */
    /** Choose how to be paid: the verified bank account (from onboarding) or PayPal. */
    public function payoutMethod(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');
        $data = $request->validate([
            'method' => ['required', \Illuminate\Validation\Rule::in(SellerPayouts::METHODS)],
            'paypal_email' => ['required_if:method,paypal', 'nullable', 'email', 'max:160'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            // Paid in another currency: the seller confirms their account can receive it.
            'currency_confirmed' => ['sometimes', 'boolean'],
        ]);
        abort_unless(SellerPayouts::fees($seller->shop?->market)[$data['method']]['enabled'], 422, 'That payout method isn’t offered in your country.');
        $details = (array) $seller->payout_details;
        // The currency they want to be paid in — only one the method allows in their country.
        if (! empty($data['currency'])) {
            $allowed = SellerPayouts::fees($seller->shop?->market)[$data['method']]['currencies'];
            abort_unless(in_array(strtolower($data['currency']), $allowed, true), 422, 'That currency isn’t available for this payout method.');
            $foreign = strtolower($data['currency']) !== Market::currency($seller->shop?->market);
            abort_if($foreign && empty($data['currency_confirmed']), 422, 'Confirm that your '.($data['method'] === 'paypal' ? 'PayPal account' : 'bank account').' can receive '.strtoupper($data['currency']).'.');
            $details['payout_currency'] = strtolower($data['currency']);
            $details['currency_confirmed_at'] = $foreign ? now()->toIso8601String() : null;
        }
        if ($data['method'] === 'bank') {
            abort_if(empty($details['account_number']), 422, 'Add your bank account first (Seller Center → Bank account).');
        } else {
            $details['paypal_email'] = strtolower(trim($data['paypal_email']));
        }
        $seller->forceFill(['payout_method' => $data['method'], 'payout_details' => $details])->save();

        $fresh = $seller->fresh('shop');

        return response()->json(['data' => ['payout_method' => $seller->payout_method, 'paypal_email' => $details['paypal_email'] ?? null, 'payout_currency' => SellerPayouts::currencyFor($fresh, $data['method']), 'payout_blocker' => SellerPayouts::blocker($fresh)]]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->with('shop')->first();
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');
        // Bank verification is enforced only when admin turned it on for this seller.
        abort_if($why = SellerPayouts::blocker($seller), 422, (string) $why);

        // Optional amount (Upwork-style "withdraw"): between the minimum and what's requestable now.
        $amount = $request->validate(['amount_cents' => ['sometimes', 'nullable', 'integer', 'min:1']])['amount_cents'] ?? null;

        $payoutRequest = DB::transaction(function () use ($seller, $amount): PayoutRequest {
            // Lock the shop row so a double-click can't open two requests.
            $shop = Shop::whereKey($seller->shop->id)->lockForUpdate()->first();
            abort_if($shop->payoutRequests()->where('status', 'pending')->exists(), 422, 'You already have a payout request waiting.');

            $min = SellerLedger::minPayoutCents($shop->market);
            abort_if(SellerLedger::availableCents($shop) < $min, 422, 'Your available balance (past the return window) needs to reach '.Money::format($min, Market::currency($shop->market)).' first.');

            $requestable = SellerLedger::requestableCents($shop);
            abort_if($amount !== null && $amount > $requestable, 422, 'You can request up to '.Money::format($requestable, Market::currency($shop->market)).' right now.');
            abort_if($amount !== null && $amount < $min, 422, 'The minimum payout is '.Money::format($min, Market::currency($shop->market)).'.');

            return $shop->payoutRequests()->create([
                'amount_cents' => $amount ?? $requestable,
                'status' => 'pending',
            ]);
        });

        return response()->json(['data' => $payoutRequest], 201);
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
