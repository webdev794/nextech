<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\OtpService;
use App\Support\Branding;
use App\Support\CheckoutFees;
use App\Support\Country;
use App\Support\CourierCredentials;
use App\Support\FooterConfig;
use App\Support\Fx;
use App\Support\Market;
use App\Support\Payments;
use App\Support\RiderLedger;
use App\Support\SalesTax;
use App\Support\SellerFulfillment;
use App\Support\SellerLedger;
use App\Support\SellerShipping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSettingController extends Controller
{
    /** Purpose used with OtpService for the secure-access challenge (otp mode). */
    private const SECURE_OTP_PURPOSE = 'admin_secure_access';

    /** Header the client sends the unlock token back in. */
    private const UNLOCK_HEADER = 'X-Secure-Access';

    public function __construct(private readonly OtpService $otp)
    {
    }

    /** Editable checkout-fee keys and their validation rules. */
    private const FEE_RULES = [
        'delivery_mode' => ['sometimes', 'in:fixed,distance'],
        'delivery_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        'delivery_near_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        'delivery_far_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        'free_delivery_threshold_cents' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
        'handling_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        'small_cart_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        'small_cart_min_cents' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
        'tax_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
    ];

    /** Storefront branding keys. */
    private const BRANDING_RULES = [
        'store_name' => ['sometimes', 'string', 'max:60'],
        'tagline' => ['sometimes', 'nullable', 'string', 'max:120'],
        'logo_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        'favicon_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        'theme' => ['sometimes', 'in:light,dark'],
        'layout_width' => ['sometimes', 'in:boxed,full'],
        'color_brand' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'color_accent' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'color_heading' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
    ];

    /** Payment-provider credentials. */
    private const PAYMENT_RULES = [
        'stripe_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        'stripe_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
        'stripe_webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
    ];

    /** Real-courier-provider credentials. */
    private const COURIER_RULES = [
        'courier_provider' => ['sometimes', 'in:mock,real'],
        'courier_base_url' => ['sometimes', 'nullable', 'string', 'max:255'],
        'courier_account_code' => ['sometimes', 'nullable', 'string', 'max:255'],
        'courier_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        'courier_api_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
        // AfterShip live tracking (LiveTracking).
        'tracking_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        'tracking_webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
    ];

    /** Footer content (a nested blob, sanitised by FooterConfig). */
    private const FOOTER_RULES = [
        'footer' => ['sometimes', 'array'],
        'footer.copyright' => ['sometimes', 'nullable', 'string', 'max:160'],
        'footer.app_store_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        'footer.play_store_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        'footer.socials' => ['sometimes', 'array'],
        'footer.socials.*' => ['nullable', 'string', 'max:2048'],
        'footer.links' => ['sometimes', 'array', 'max:12'],
        'footer.links.*.label' => ['nullable', 'string', 'max:40'],
        'footer.links.*.url' => ['nullable', 'string', 'max:2048'],
        'footer.bg_color' => ['sometimes', 'nullable', 'string', 'max:7'],
        'footer.text_color' => ['sometimes', 'nullable', 'string', 'max:7'],
    ];

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    /**
     * OTP-mode only: e-mail the signed-in admin a code for the Secure access
     * section. In password mode there is nothing to send.
     */
    public function secureAccessChallenge(Request $request): JsonResponse
    {
        if ($this->unlockMethod() !== 'otp') {
            return response()->json(['data' => ['method' => 'password']]);
        }

        $email = $request->user()->email;

        try {
            $this->otp->issue($email, self::SECURE_OTP_PURPOSE);
            $message = 'We emailed a verification code to '.$this->maskEmail($email).'.';
        } catch (\Illuminate\Validation\ValidationException) {
            $message = 'A code was sent recently. Check '.$this->maskEmail($email).'.';
        }

        return response()->json(['data' => ['method' => 'otp', 'email' => $this->maskEmail($email), 'message' => $message]], 202);
    }

    /**
     * Unlock the Secure access section. Accepts `password` (default mode) or
     * `code` (otp mode) and mints a short-lived token the client sends back in
     * the X-Secure-Access header for sensitive writes.
     */
    public function secureAccessUnlock(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->unlockMethod() === 'otp') {
            $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
            $ok = $this->otp->verify($user->email, self::SECURE_OTP_PURPOSE, $validated['code']);
            $failMessage = 'That code is invalid or has expired. Request a new one.';
        } else {
            $request->validate(['password' => ['required', 'string']]);
            $ok = Hash::check($request->input('password'), (string) $user->password);
            $failMessage = 'That password is incorrect.';
        }

        if (! $ok) {
            return response()->json(['message' => $failMessage], 422);
        }

        $token = Str::random(48);
        Cache::put($this->grantKey($user->id), hash('sha256', $token), now()->addMinutes($this->ttlMinutes()));

        return response()->json(['data' => [
            'token' => $token,
            'expires_in' => $this->ttlMinutes() * 60,
            'account' => $this->accountPayload($user),
            'payments' => $this->payload()['payments'],
            'courier' => $this->payload()['courier'],
        ]]);
    }

    /**
     * Change the signed-in admin's own name / e-mail / phone. Requires the
     * Secure access unlock token.
     */
    /** Settings → Charges → "Fetch automatically": fill the US state tax table from the lookup. */
    public function fetchSalesTaxStates(): JsonResponse
    {
        $result = SalesTax::fetchStateRates();

        return response()->json(['data' => $this->payload(), 'result' => $result]);
    }

    /** Secure access: sellers kept on an older commission rate, with their market's current rate. */
    public function keptRates(Request $request): JsonResponse
    {
        $this->assertUnlocked($request);
        $rates = SellerLedger::marketRates();
        $shops = \App\Models\Shop::whereNotNull('commission_rate_bps')->orderBy('name')->get(['id', 'name', 'market', 'commission_rate_bps']);

        return response()->json(['data' => $shops->map(fn ($shop) => [
            'id' => $shop->id,
            'name' => $shop->name,
            'market' => $shop->market,
            'kept_rate_bps' => (int) $shop->commission_rate_bps,
            'current_rate_bps' => $rates[$shop->market] ?? SellerLedger::rate($shop->market),
        ])->values()]);
    }

    /** Secure access: move the chosen sellers off their kept rate onto their market's current one. */
    public function releaseKeptRates(Request $request): JsonResponse
    {
        $this->assertUnlocked($request);
        $data = $request->validate(['shop_ids' => ['required', 'array', 'min:1', 'max:1000'], 'shop_ids.*' => ['integer']]);
        $moved = \App\Models\Shop::whereIn('id', $data['shop_ids'])->whereNotNull('commission_rate_bps')->update(['commission_rate_bps' => null]);

        return response()->json(['data' => ['moved' => $moved]]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $this->assertUnlocked($request);
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        if (array_key_exists('phone', $validated) && $validated['phone'] !== null) {
            $validated['phone'] = trim($validated['phone']);
        }

        $user->update($validated);

        return response()->json(['data' => $this->accountPayload($user->fresh())]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'cod_enabled' => ['sometimes', 'boolean'],
                'rider_auto_assign' => ['sometimes', 'boolean'],
                'nextech_pickup' => ['sometimes', Rule::in(['available', 'disabled', 'hidden'])],
                // Sellers' own local delivery, and how hard sellers are chased for order updates.
                'seller_local_delivery' => ['sometimes', Rule::in(['available', 'hidden'])],
                // NexTech stock: own riders within each store's radius (courier beyond), or courier for everything.
                'nextech_own_delivery' => ['sometimes', Rule::in(['on', 'off'])],
                // Sellers need admin approval (export ID, document, signed declaration) to ship abroad.
                'intl_requires_approval' => ['sometimes', 'boolean'],
                // Deal sections, filled automatically (App\Support\DealSections).
                'deal_rules' => ['sometimes', 'array'],
                'deal_rules.*' => ['integer', 'min:1', 'max:500'],
                'seller_local_max_km' => ['sometimes', 'numeric', 'min:1', 'max:100'],
                'seller_update_rules' => ['sometimes', 'array'],
                'seller_update_rules.pack_hours' => ['required_with:seller_update_rules', 'integer', 'min:1', 'max:168'],
                'seller_update_rules.repeat_hours' => ['required_with:seller_update_rules', 'integer', 'min:1', 'max:72'],
                'seller_update_rules.escalate_hours' => ['required_with:seller_update_rules', 'integer', 'min:1', 'max:168'],
                'nextech_label_mode' => ['sometimes', Rule::in(['auto', 'manual'])],
                'decoration_min_products' => ['sometimes', 'integer', 'min:0', 'max:1000'],
                'decoration_spot_check_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
                'active_countries' => ['sometimes', 'array'],
                'active_countries.*' => ['string', Rule::in(array_keys(config('countries', [])))],
                'commission_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
                // New-seller commission (Secure access): null = same rate as established sellers.
                'new_seller_commission_rate_bps' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
                'new_seller_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
                // With a commission change: also move existing sellers to it (default: they keep their rate).
                'commission_apply_existing' => ['sometimes', 'boolean'],
                // Bank-rules note sellers see next to "Request payout", per country (blank = default text).
                'payout_notes' => ['sometimes', 'array'],
                'payout_notes.*' => ['nullable', 'string', 'max:1000'],
                // Payout fees per country and method: { IN: { bank: {fixed_cents, bps}, paypal: {...} } }.
                'payout_fees' => ['sometimes', 'array'],
                'payout_fees.*.*.fixed_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
                'payout_fees.*.*.bps' => ['nullable', 'integer', 'min:0', 'max:5000'],
                'payout_fees.*.*.min_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
                'payout_fees.*.*.currency' => ['nullable', 'string', 'size:3'],
                'payout_fees.*.*.currencies' => ['nullable', 'array'],
                'payout_fees.*.*.enabled' => ['nullable', 'boolean'],
                'payout_fees.*.*.currencies.*' => ['string', 'size:3'],
                // US sales tax: by state/ZIP or one flat rate; per-state edits; ZIP-lookup key (Secure access).
                'sales_tax_mode' => ['sometimes', Rule::in(['state', 'flat'])],
                'sales_tax_states' => ['sometimes', 'array'],
                'sales_tax_states.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'sales_tax_reset_states' => ['sometimes', 'boolean'],
                'sales_tax_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'sales_tax_clear_cache' => ['sometimes', 'boolean'],
                'min_payout_cents' => ['sometimes', 'integer', 'min:0'],
                'max_payout_cents' => ['sometimes', 'integer', 'min:0'],
                'daily_payout_cap_cents' => ['sometimes', 'integer', 'min:0'],
                'return_window_days' => ['sometimes', 'integer', 'min:0', 'max:365', 'lte:max_return_days'],
                'max_return_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
                'return_pickup_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
                'label_postage_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
                'rider_base_pay_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
                'rider_per_mile_cents' => ['sometimes', 'integer', 'min:0', 'max:100000'],
                'rider_min_payout_cents' => ['sometimes', 'integer', 'min:0'],
                'rider_max_payout_cents' => ['sometimes', 'integer', 'min:0'],
                // Other markets (e.g. India): fees + payout limits in their own currency.
                'market' => ['sometimes', 'string', Rule::in(array_keys(config('markets', [])))],
                'market_fees' => ['sometimes', 'array'],
                'market_payouts' => ['sometimes', 'array'],
                'market_payouts.min_payout_cents' => ['sometimes', 'integer', 'min:0'],
                'market_payouts.max_payout_cents' => ['sometimes', 'integer', 'min:0'],
                'market_payouts.daily_payout_cap_cents' => ['sometimes', 'integer', 'min:0'],
                'market_payouts.return_pickup_fee_cents' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
                'market_payouts.label_postage_cents' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
                'market_payouts.commission_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
                'home_market' => ['sometimes', 'string', Rule::in(array_keys(config('markets', [])))],
                'market_rider_pay' => ['sometimes', 'array'],
                'market_rider_pay.base_cents' => ['sometimes', 'integer', 'min:0'],
                'market_rider_pay.per_mile_cents' => ['sometimes', 'integer', 'min:0'],
                'market_rider_pay.min_payout_cents' => ['sometimes', 'integer', 'min:0'],
                'market_rider_pay.max_payout_cents' => ['sometimes', 'integer', 'min:0'],
                // NexTech's own legal identity per country, printed as "Sold by" on bills for its own items.
                // Digital downloads: upload limits (MB) — keep small on shared hosting.
                'digital_max_file_mb' => ['sometimes', 'integer', 'min:1', 'max:4096'],
                // Seller cash on delivery: off / only approved sellers / all, and the "owed" limit per country (cents).
                'seller_cod_mode' => ['sometimes', Rule::in(\App\Support\SellerCod::MODES)],
                'seller_cod_max_owed' => ['sometimes', 'array'],
                'seller_cod_max_owed.*' => ['integer', 'min:0', 'max:100000000'],
                'digital_max_product_mb' => ['sometimes', 'integer', 'min:1', 'max:20480'],
                'business_details' => ['sometimes', 'array'],
                'business_details.*' => ['nullable', 'array'],
                'business_details.*.legal_name' => ['nullable', 'string', 'max:160'],
                'business_details.*.address' => ['nullable', 'string', 'max:500'],
                'business_details.*.tax_number' => ['nullable', 'string', 'max:40'],
                'grievance_officer' => ['sometimes', 'nullable', 'array'],
                'grievance_officer.name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'grievance_officer.designation' => ['sometimes', 'nullable', 'string', 'max:120'],
                'grievance_officer.email' => ['sometimes', 'nullable', 'email', 'max:190'],
                'grievance_officer.phone' => ['sometimes', 'nullable', 'string', 'max:32'],
                'grievance_officer.address' => ['sometimes', 'nullable', 'string', 'max:500'],
                // Cross-border currency conversion (Fx): platform margin, and optional fixed rates per 1 USD.
                'fx_margin_bps' => ['sometimes', 'integer', 'min:0', 'max:2000'],
                'fx_manual' => ['sometimes', 'array'],
                'fx_manual.*' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
                'fx_refresh' => ['sometimes', 'boolean'],
            ]
            + collect(self::FEE_RULES)->mapWithKeys(fn ($rules, $key) => ['market_fees.'.$key => $rules])->all()
            + self::FEE_RULES + self::BRANDING_RULES + self::PAYMENT_RULES + self::COURIER_RULES + self::FOOTER_RULES
        );

        $ratesBefore = SellerLedger::marketRates();

        if (array_key_exists('cod_enabled', $validated)) {
            Setting::put('cod_enabled', (bool) $validated['cod_enabled']);
        }

        // Options that need something else set up first (a hidden / unused option needs nothing).
        $courierConnected = (fn ($c) => $c['provider'] === 'real' && $c['base_url'] !== '' && $c['api_key'] !== '')(CourierCredentials::current());
        $ownStores = \App\Models\Store::query()->exists();
        if (($validated['nextech_label_mode'] ?? null) === 'auto' && SellerFulfillment::labelMode() !== 'auto' && ! $courierConnected) {
            abort(422, 'Courier API labels need a real courier connected first (Secure access → Courier).');
        }
        if (($validated['nextech_pickup'] ?? null) === 'available' && SellerShipping::nextechPickup() !== 'available' && ! $ownStores && ! $courierConnected) {
            abort(422, 'To collect and deliver for sellers, first add a '.Branding::name().' store with riders (Stores, Riders) or connect a courier (Secure access → Courier).');
        }
        if (($validated['delivery_mode'] ?? null) === 'distance' && CheckoutFees::current()['delivery_mode'] !== 'distance' && ! $ownStores) {
            abort(422, 'Distance-based delivery fees are measured from your stores — add a store first, or use a fixed fee.');
        }

        if (array_key_exists('nextech_label_mode', $validated)) {
            Setting::put('nextech_label_mode', $validated['nextech_label_mode']);
        }

        foreach (['decoration_min_products' => 'intval', 'decoration_spot_check_rate' => 'floatval'] as $key => $cast) {
            if (array_key_exists($key, $validated)) {
                Setting::put($key, $cast($validated[$key]));
            }
        }

        if (array_key_exists('nextech_pickup', $validated)) {
            Setting::put('nextech_pickup', $validated['nextech_pickup']);
        }
        if (($validated['nextech_own_delivery'] ?? null) === 'on' && Setting::get('nextech_own_delivery', 'on') === 'off'
            && ! (\App\Models\Store::query()->where('is_active', true)->exists() && \App\Models\User::query()->where('is_rider', true)->exists())) {
            abort(422, 'Add an active store and at least one rider first (Stores, Riders).');
        }
        if (array_key_exists('deal_rules', $validated)) {
            Setting::put('deal_rules', array_intersect_key(array_map('intval', $validated['deal_rules']), \App\Support\DealSections::DEFAULTS) + \App\Support\DealSections::settings());
            \App\Support\DealSections::forget();
        }
        if (array_key_exists('intl_requires_approval', $validated)) {
            // Turning it on: sellers already shipping abroad keep doing so (marked approved).
            if ($validated['intl_requires_approval'] && ! \App\Support\SellerIntl::requiresApproval()) {
                \App\Models\Shop::query()->whereNotNull('intl_shipping')->get()
                    ->reject(fn ($shop) => \App\Support\SellerIntl::status($shop) === 'approved')
                    ->each(fn ($shop) => $shop->update(['intl_approval' => ['status' => 'approved', 'reason' => 'Already selling abroad when approval was switched on', 'reviewed_at' => now()->toIso8601String()] + (array) $shop->intl_approval]));
            }
            Setting::put('intl_requires_approval', (bool) $validated['intl_requires_approval']);
        }
        if (array_key_exists('nextech_own_delivery', $validated)) {
            Setting::put('nextech_own_delivery', $validated['nextech_own_delivery']);
        }
        if (array_key_exists('seller_local_delivery', $validated)) {
            Setting::put('seller_local_delivery', $validated['seller_local_delivery']);
        }
        if (array_key_exists('seller_local_max_km', $validated)) {
            Setting::put('seller_local_max_km', round((float) $validated['seller_local_max_km'], 1));
        }
        if (array_key_exists('seller_update_rules', $validated)) {
            Setting::put('seller_update_rules', array_map('intval', array_intersect_key($validated['seller_update_rules'], array_flip(['pack_hours', 'repeat_hours', 'escalate_hours']))));
        }

        if (array_key_exists('rider_auto_assign', $validated)) {
            Setting::put('rider_auto_assign', (bool) $validated['rider_auto_assign']);
        }

        if (array_key_exists('active_countries', $validated)) {
            Setting::put('active_countries', array_values(array_unique(array_map('strtoupper', $validated['active_countries']))));
        }

        if (array_key_exists('commission_rate_bps', $validated)) {
            Setting::put('commission_rate_bps', (int) $validated['commission_rate_bps']);
        }

        if (array_key_exists('payout_fees', $validated)) {
            $fees = (array) Setting::get('payout_fees', []);
            foreach ($validated['payout_fees'] as $code => $byMethod) {
                $byMethod = array_intersect_key((array) $byMethod, array_flip(\App\Support\SellerPayouts::METHODS));
                abort_if($byMethod && ! collect($byMethod)->contains(fn ($f) => (bool) ($f['enabled'] ?? true)), 422, 'Keep at least one payout method on for '.strtoupper($code).'.');
                foreach ($byMethod as $method => $f) {
                    $fees[strtoupper($code)][$method] = ['fixed_cents' => (int) ($f['fixed_cents'] ?? 0), 'min_cents' => (int) ($f['min_cents'] ?? 0), 'bps' => (int) ($f['bps'] ?? 0), 'currency' => strtolower((string) ($f['currency'] ?? Market::currency($code))), 'currencies' => array_values(array_map('strtolower', (array) ($f['currencies'] ?? []))), 'enabled' => (bool) ($f['enabled'] ?? true)];
                }
            }
            Setting::put('payout_fees', $fees);
        }

        if (array_key_exists('payout_notes', $validated)) {
            $notes = array_merge((array) Setting::get('payout_notes', []), collect($validated['payout_notes'])->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => trim((string) $v)])->all());
            Setting::put('payout_notes', array_filter($notes, fn ($v) => $v !== ''));
        }

        if (array_key_exists('sales_tax_mode', $validated)) {
            Setting::put('sales_tax_mode', $validated['sales_tax_mode']);
        }
        if (array_key_exists('sales_tax_states', $validated)) {
            // Blank = null: that state uses the default rate.
            $known = array_keys((array) config('sales_tax.states', []));
            Setting::put('sales_tax_states', collect($validated['sales_tax_states'])->only($known)->map(fn ($v) => $v === null ? null : (int) $v)->all());
        }
        if (! empty($validated['sales_tax_reset_states'])) {
            Setting::put('sales_tax_states', []);
        }
        if (array_key_exists('sales_tax_api_key', $validated) || ! empty($validated['sales_tax_clear_cache'])) {
            $this->assertUnlocked($request);
            if (array_key_exists('sales_tax_api_key', $validated) && trim((string) $validated['sales_tax_api_key']) !== '') {
                Setting::put('sales_tax_api_key', trim((string) $validated['sales_tax_api_key']));
            }
            if (! empty($validated['sales_tax_clear_cache'])) {
                \App\Models\SalesTaxRate::query()->delete();
            }
        }

        // New-seller commission lives behind the Secure access unlock.
        if (array_key_exists('new_seller_commission_rate_bps', $validated) || array_key_exists('new_seller_days', $validated)) {
            $this->assertUnlocked($request);
            if (array_key_exists('new_seller_commission_rate_bps', $validated)) {
                Setting::put('new_seller_commission_rate_bps', $validated['new_seller_commission_rate_bps'] === null ? null : (int) $validated['new_seller_commission_rate_bps']);
            }
            if (array_key_exists('new_seller_days', $validated)) {
                Setting::put('new_seller_days', (int) $validated['new_seller_days']);
            }
        }

        foreach (['min_payout_cents', 'max_payout_cents', 'daily_payout_cap_cents', 'return_window_days', 'max_return_days', 'return_pickup_fee_cents', 'label_postage_cents', 'rider_base_pay_cents', 'rider_per_mile_cents', 'rider_min_payout_cents', 'rider_max_payout_cents'] as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::put($key, (int) $validated[$key]);
            }
        }

        $this->mergeInto('checkout_fees', array_intersect_key($validated, self::FEE_RULES));

        $market = strtoupper($validated['market'] ?? '');
        if ($market !== '' && ! Market::usesLegacySettings($market)) {
            $this->mergeInto('checkout_fees_'.$market, array_intersect_key((array) ($validated['market_fees'] ?? []), self::FEE_RULES));
            $this->mergeInto('payouts_'.$market, (array) ($validated['market_payouts'] ?? []));
            $this->mergeInto('rider_pay_'.$market, (array) ($validated['market_rider_pay'] ?? []));
        }

        if (array_key_exists('home_market', $validated)) {
            Setting::put('home_market', strtoupper($validated['home_market']));
        }

        if (array_key_exists('fx_margin_bps', $validated) || array_key_exists('fx_manual', $validated)) {
            $fx = Fx::settings();
            if (array_key_exists('fx_margin_bps', $validated)) {
                $fx['margin_bps'] = (int) $validated['fx_margin_bps'];
            }
            foreach ((array) ($validated['fx_manual'] ?? []) as $currency => $rate) {
                $fx['manual'][strtolower((string) $currency)] = $rate !== null && $rate !== '' ? (float) $rate : null;
            }
            Setting::put('fx', $fx);
        }
        if (! empty($validated['fx_refresh'])) {
            Fx::refresh();
        }

        if (array_key_exists('seller_cod_mode', $validated)) {
            Setting::put('seller_cod_mode', $validated['seller_cod_mode']);
        }
        if (array_key_exists('seller_cod_max_owed', $validated)) {
            Setting::put('seller_cod_max_owed', array_merge((array) Setting::get('seller_cod_max_owed', []), array_intersect_key(array_map('intval', $validated['seller_cod_max_owed']), array_flip(Market::codes()))));
        }
        foreach (['digital_max_file_mb', 'digital_max_product_mb'] as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::put($key, (int) $validated[$key]);
            }
        }
        if (array_key_exists('business_details', $validated)) {
            $details = (array) Setting::get('business_details', []);
            foreach ((array) $validated['business_details'] as $code => $row) {
                $code = strtoupper((string) $code);
                abort_unless(in_array($code, Market::codes(), true), 422, 'Unknown country.');
                $row = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row), fn ($v) => $v !== null && $v !== '');
                if ($row) {
                    $details[$code] = $row;
                } else {
                    unset($details[$code]);
                }
            }
            Setting::put('business_details', $details ?: null);
        }
        if (array_key_exists('grievance_officer', $validated)) {
            $officer = array_filter((array) $validated['grievance_officer'], fn ($v) => $v !== null && $v !== '');
            Setting::put('grievance_officer', $officer ?: null);
        }
        $this->mergeInto('branding', array_intersect_key($validated, self::BRANDING_RULES));

        if (array_key_exists('footer', $validated)) {
            Setting::put('footer', FooterConfig::sanitize((array) $validated['footer']));
        }

        // Payment settings live behind the Secure access unlock.
        $payments = array_intersect_key($validated, self::PAYMENT_RULES);
        if ($payments !== []) {
            $this->assertUnlocked($request);
        }

        // Secrets: only overwrite when a non-empty value is supplied, so leaving
        // the field blank keeps the current key. The publishable key can be
        // cleared (it is not sensitive).
        foreach (['stripe_secret', 'stripe_webhook_secret'] as $secret) {
            if (array_key_exists($secret, $payments) && trim((string) $payments[$secret]) === '') {
                unset($payments[$secret]);
            }
        }
        $this->mergeInto('payments', $payments);

        // Courier credentials live behind the same Secure access unlock.
        $courier = array_intersect_key($validated, self::COURIER_RULES);
        if ($courier !== []) {
            $this->assertUnlocked($request);
        }

        foreach (['courier_api_key', 'courier_api_secret', 'tracking_api_key', 'tracking_webhook_secret'] as $secret) {
            if (array_key_exists($secret, $courier) && trim((string) $courier[$secret]) === '') {
                unset($courier[$secret]);
            }
        }
        $this->mergeInto('courier', $courier);

        // Commission changed: existing sellers keep their rate unless the admin applies it to them too.
        $apply = (bool) ($validated['commission_apply_existing'] ?? false);
        $forced = [];
        if ($apply && array_key_exists('commission_rate_bps', $validated)) {
            $forced = array_values(array_filter(Market::codes(), fn ($code) => Market::usesLegacySettings($code) || ! isset(((array) Setting::get('payouts_'.$code, []))['commission_rate_bps'])));
        }
        if ($apply && isset($validated['market_payouts']['commission_rate_bps'], $validated['market'])) {
            $forced[] = strtoupper($validated['market']);
        }
        SellerLedger::lockShopRates($ratesBefore, $apply, $forced);

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function mergeInto(string $key, array $values): void
    {
        if ($values === []) {
            return;
        }

        $existing = Setting::get($key, []);
        Setting::put($key, array_merge(is_array($existing) ? $existing : [], $values));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $stripe = Payments::stripe();
        $courier = CourierCredentials::current();

        return [
            'cod_enabled' => (bool) Setting::get('cod_enabled', false),
            'rider_auto_assign' => (bool) Setting::get('rider_auto_assign', true),
            // NexTech's own delivery network (rider auto-assign applies only to these).
            'own_stores_count' => \App\Models\Store::query()->count(),
            'courier_connected' => (fn ($c) => $c['provider'] === 'real' && $c['base_url'] !== '' && $c['api_key'] !== '')(CourierCredentials::current()),
            'riders_count' => \App\Models\User::query()->where('is_rider', true)->count(),
            'nextech_pickup' => SellerShipping::nextechPickup(),
            'seller_local_delivery' => SellerShipping::localDeliveryOffered() ? 'available' : 'hidden',
            'nextech_own_delivery' => Setting::get('nextech_own_delivery', 'on') === 'off' ? 'off' : 'on',
            'intl_requires_approval' => \App\Support\SellerIntl::requiresApproval(),
            'deal_rules' => \App\Support\DealSections::settings(),
            'seller_local_max_km' => SellerShipping::localMaxKm(),
            'seller_update_rules' => \App\Support\SellerProgress::rules(),
            // Couriers per country, for entering a hand-booked courier on NexTech orders.
            'carriers' => collect(Market::codes())->mapWithKeys(fn ($code) => [$code => collect(Market::carriers($code))->map(fn ($c, $key) => ['value' => $key, 'label' => $c[0]])->values()]),
            'nextech_label_mode' => SellerFulfillment::labelMode(),
            'decoration_min_products' => \App\Support\StoreDecorations::minProducts(),
            'decoration_spot_check_rate' => \App\Support\StoreDecorations::spotCheckRate(),
            'active_countries' => Country::active(),
            'fx' => Fx::status(),
            'all_countries' => collect(Country::all())->map(fn (array $c) => ['code' => $c['code'], 'name' => $c['name']])->values()->all(),
            // The US forms (original settings keys).
            'commission_rate_bps' => SellerLedger::rate('US'),
            'new_seller_commission_rate_bps' => SellerLedger::newSellerRateBps(),
            'new_seller_days' => SellerLedger::newSellerDays(),
            'sales_tax' => SalesTax::adminPayload(),
            'payout_notes' => collect(Market::codes())->mapWithKeys(fn ($code) => [$code => SellerLedger::payoutNote($code)]),
            'payout_fees' => collect(Market::codes())->mapWithKeys(fn ($code) => [$code => \App\Support\SellerPayouts::fees($code)]),
            'payout_currency_options' => collect(Market::codes())->mapWithKeys(fn ($code) => [$code => \App\Support\SellerPayouts::currencyOptions(Market::currency($code))]),
            // Sellers kept on an older commission rate, per market.
            'kept_rate_sellers' => \App\Models\Shop::whereNotNull('commission_rate_bps')->selectRaw('market, count(*) as n')->groupBy('market')->pluck('n', 'market'),
            'min_payout_cents' => SellerLedger::minPayoutCents('US'),
            'max_payout_cents' => SellerLedger::maxPayoutCents('US'),
            'daily_payout_cap_cents' => SellerLedger::dailyPayoutCapCents('US'),
            'return_window_days' => SellerLedger::returnWindowDays(),
            'max_return_days' => SellerLedger::maxReturnDays(),
            'return_pickup_fee_cents' => SellerLedger::returnPickupFeeCents('US'),
            'label_postage_cents' => SellerLedger::labelPostageCents('US'),
            'rider_base_pay_cents' => RiderLedger::baseCents('US'),
            'rider_per_mile_cents' => RiderLedger::perMileCents('US'),
            'rider_min_payout_cents' => RiderLedger::minPayoutCents('US'),
            'rider_max_payout_cents' => RiderLedger::maxPayoutCents('US'),
            ...CheckoutFees::current('US'),
            'home_market' => Market::home(),
            'home_market_name' => Country::find(Market::home())['name'] ?? Market::home(),
            'home_currency' => Market::currency(Market::home()),
            // Each non-home market's own fees and payout limits (its currency).
            // Every open country for the admin's currency switch.
            'all_markets' => collect(Market::codes())->map(fn ($code) => ['code' => $code, 'name' => Country::find($code)['name'] ?? $code, 'currency' => Market::currency($code)])->values(),
            // Countries with their own (non-US) charge settings.
            'markets' => collect(Market::codes())->reject(fn ($code) => Market::usesLegacySettings($code))->map(fn ($code) => [
                'code' => $code,
                'name' => Country::find($code)['name'] ?? $code,
                'currency' => Market::currency($code),
                'fees' => CheckoutFees::current($code),
                'payouts' => [
                    'min_payout_cents' => SellerLedger::minPayoutCents($code),
                    'max_payout_cents' => SellerLedger::maxPayoutCents($code),
                    'daily_payout_cap_cents' => SellerLedger::dailyPayoutCapCents($code),
                    'return_pickup_fee_cents' => SellerLedger::returnPickupFeeCents($code),
                    'label_postage_cents' => SellerLedger::labelPostageCents($code),
                    'commission_rate_bps' => SellerLedger::rate($code),
                ],
                'rider_pay' => [
                    'base_cents' => RiderLedger::baseCents($code),
                    'per_mile_cents' => RiderLedger::perMileCents($code),
                    'min_payout_cents' => RiderLedger::minPayoutCents($code),
                    'max_payout_cents' => RiderLedger::maxPayoutCents($code),
                ],
            ])->values(),
            'grievance_officer' => Setting::get('grievance_officer'),
            'business_details' => (object) (Setting::get('business_details') ?? []),
            'setup_checklist' => $this->setupChecklist(),
            'digital_max_file_mb' => (int) Setting::get('digital_max_file_mb', 50),
            'seller_cod_mode' => \App\Support\SellerCod::mode(),
            'seller_cod_max_owed' => collect(Market::codes())->mapWithKeys(fn ($c) => [$c => \App\Support\SellerCod::maxOwedCents($c)]),
            'digital_max_product_mb' => (int) Setting::get('digital_max_product_mb', 200),
            'branding' => Branding::current(),
            'footer' => FooterConfig::current(),
            'secure_access' => ['method' => $this->unlockMethod()],
            'payments' => [
                // Publishable key is safe to echo; secrets are only hinted.
                'stripe_key' => $stripe['key'],
                'stripe_mode' => $stripe['mode'],
                'stripe_secret_set' => $stripe['secret'] !== '',
                'stripe_secret_hint' => self::hint($stripe['secret']),
                'stripe_webhook_secret_set' => $stripe['webhook_secret'] !== '',
                'stripe_webhook_secret_hint' => self::hint($stripe['webhook_secret']),
            ],
            'courier' => [
                'provider' => $courier['provider'],
                'base_url' => $courier['base_url'],
                'account_code' => $courier['account_code'],
                'api_key_set' => $courier['api_key'] !== '',
                'api_key_hint' => self::hint($courier['api_key']),
                'api_secret_set' => $courier['api_secret'] !== '',
                'api_secret_hint' => self::hint($courier['api_secret']),
                'tracking_api_key_set' => $courier['tracking_api_key'] !== '',
                'tracking_api_key_hint' => self::hint($courier['tracking_api_key']),
                'tracking_webhook_secret_set' => $courier['tracking_webhook_secret'] !== '',
                'tracking_webhook_secret_hint' => self::hint($courier['tracking_webhook_secret']),
                'tracking_webhook_url' => url('/api/webhooks/aftership'),
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function accountPayload(\App\Models\User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone];
    }

    private function unlockMethod(): string
    {
        return config('secure_access.method') === 'otp' ? 'otp' : 'password';
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('secure_access.ttl_minutes', 15));
    }

    /**
     * Settings → Setup checklist: the essentials without which part of the
     * store can't work (payments, delivery, bills, seller payouts), each with
     * whether it's done and what to do. Optional features aren't listed.
     *
     * @return list<array{key: string, label: string, ok: bool, hint: string}>
     */
    private function setupChecklist(): array
    {
        $stripe = Payments::stripe();
        $cardsReady = $stripe['key'] !== '' && $stripe['secret'] !== '';
        $courier = CourierCredentials::current();
        $courierConnected = $courier['provider'] === 'real' && $courier['base_url'] !== '' && $courier['api_key'] !== '';
        $stores = \App\Models\Store::query()->count();
        $riders = \App\Models\User::query()->where('is_rider', true)->count();
        $pickup = SellerShipping::nextechPickup();
        $details = (array) (Setting::get('business_details') ?? []);
        $markets = Market::codes();

        $items = [
            ['key' => 'store_name', 'label' => 'Store name', 'ok' => trim((string) (Branding::current()['store_name'] ?? '')) !== '', 'hint' => 'Store settings → name (shown on every page, email and bill).'],
            ['key' => 'payments', 'label' => 'A way for buyers to pay', 'ok' => $cardsReady || (bool) Setting::get('cod_enabled', false), 'hint' => 'Add Stripe keys (Secure access → Payments) or turn on cash on delivery.'],
            ['key' => 'business_details', 'label' => 'Business details on bills', 'ok' => collect($markets)->every(fn ($c) => trim((string) (($details[$c] ?? [])['legal_name'] ?? '')) !== '' && trim((string) (($details[$c] ?? [])['address'] ?? '')) !== ''), 'hint' => 'Settings → Business details: legal name and address for each country (printed on bills).'],
            ['key' => 'delivery', 'label' => 'A way to deliver orders', 'ok' => $pickup !== 'available' || $stores > 0 || $courierConnected, 'hint' => '“'.Branding::name().' collects & delivers” is on but there’s no store with riders and no courier — add a store and riders, connect a courier, or switch it off so sellers ship themselves.'],
        ];
        if ($stores > 0) {
            $items[] = ['key' => 'riders', 'label' => 'Riders for your stores', 'ok' => $riders > 0, 'hint' => 'Riders → add riders and link them to your stores.'];
        }
        if (SellerFulfillment::labelMode() === 'auto') {
            $items[] = ['key' => 'courier', 'label' => 'Courier connected for labels', 'ok' => $courierConnected, 'hint' => 'Secure access → Courier, or switch labels to Built-in.'];
        }

        return $items;
    }

    private function grantKey(int $userId): string
    {
        return "secure_access_grant:{$userId}";
    }

    private function assertUnlocked(Request $request): void
    {
        $token = (string) $request->header(self::UNLOCK_HEADER, '');
        $stored = Cache::get($this->grantKey($request->user()->id));

        abort_if(
            $stored === null || $token === '' || ! hash_equals($stored, hash('sha256', $token)),
            403,
            'Unlock the Secure access section first.'
        );
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $shown = mb_substr($name, 0, 1).str_repeat('•', max(1, mb_strlen($name) - 1));

        return $domain === '' ? $shown : "{$shown}@{$domain}";
    }

    private static function hint(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) <= 12
            ? str_repeat('•', strlen($value))
            : substr($value, 0, 7).str_repeat('•', 6).substr($value, -4);
    }
}
