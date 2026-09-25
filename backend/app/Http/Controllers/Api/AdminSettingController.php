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
use App\Support\Market;
use App\Support\Payments;
use App\Support\RiderLedger;
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
                'nextech_label_mode' => ['sometimes', Rule::in(['auto', 'manual'])],
                'decoration_min_products' => ['sometimes', 'integer', 'min:0', 'max:1000'],
                'decoration_spot_check_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
                'active_countries' => ['sometimes', 'array'],
                'active_countries.*' => ['string', Rule::in(array_keys(config('countries', [])))],
                'commission_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
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
                'grievance_officer' => ['sometimes', 'nullable', 'array'],
                'grievance_officer.name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'grievance_officer.designation' => ['sometimes', 'nullable', 'string', 'max:120'],
                'grievance_officer.email' => ['sometimes', 'nullable', 'email', 'max:190'],
                'grievance_officer.phone' => ['sometimes', 'nullable', 'string', 'max:32'],
                'grievance_officer.address' => ['sometimes', 'nullable', 'string', 'max:500'],
            ]
            + collect(self::FEE_RULES)->mapWithKeys(fn ($rules, $key) => ['market_fees.'.$key => $rules])->all()
            + self::FEE_RULES + self::BRANDING_RULES + self::PAYMENT_RULES + self::COURIER_RULES + self::FOOTER_RULES
        );

        if (array_key_exists('cod_enabled', $validated)) {
            Setting::put('cod_enabled', (bool) $validated['cod_enabled']);
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

        if (array_key_exists('rider_auto_assign', $validated)) {
            Setting::put('rider_auto_assign', (bool) $validated['rider_auto_assign']);
        }

        if (array_key_exists('active_countries', $validated)) {
            Setting::put('active_countries', array_values(array_unique(array_map('strtoupper', $validated['active_countries']))));
        }

        if (array_key_exists('commission_rate_bps', $validated)) {
            Setting::put('commission_rate_bps', (int) $validated['commission_rate_bps']);
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

        foreach (['courier_api_key', 'courier_api_secret'] as $secret) {
            if (array_key_exists($secret, $courier) && trim((string) $courier[$secret]) === '') {
                unset($courier[$secret]);
            }
        }
        $this->mergeInto('courier', $courier);

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
            'nextech_pickup' => SellerShipping::nextechPickup(),
            'nextech_label_mode' => SellerFulfillment::labelMode(),
            'decoration_min_products' => \App\Support\StoreDecorations::minProducts(),
            'decoration_spot_check_rate' => \App\Support\StoreDecorations::spotCheckRate(),
            'active_countries' => Country::active(),
            'all_countries' => collect(Country::all())->map(fn (array $c) => ['code' => $c['code'], 'name' => $c['name']])->values()->all(),
            // The US forms (original settings keys).
            'commission_rate_bps' => SellerLedger::rate('US'),
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
