<?php

namespace App\Http\Controllers\Api;

use App\Support\SellerCod;
use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use App\Models\ShippingTemplate;
use App\Models\Shop;
use App\Models\ShopAddress;
use App\Support\Country;
use App\Support\Market;
use App\Support\SellerFulfillment;
use App\Support\SellerShipping;
use App\Support\SellerTax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Seller Center -> My account -> Shipping settings: how the shop fulfils
 * orders, its ship-from addresses, shipping templates, and working days.
 */
class SellerShippingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->shop($request))]);
    }

    /** Fulfillment mode, weekend/holiday working days, and the free-shipping rule. */
    public function update(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'fulfillment_mode' => ['sometimes', Rule::in(SellerShipping::MODES)],
            'ships_saturday' => ['sometimes', 'boolean'],
            'ships_sunday' => ['sometimes', 'boolean'],
            'working_holidays' => ['sometimes', 'array'],
            'working_holidays.*' => [Rule::in(array_keys(SellerShipping::holidayNames($shop->market)))],
            'accept_free_shipping' => ['sometimes', 'accepted'],
            'accepts_cod' => ['sometimes', 'boolean'],
            // Countries shipped to, keyed by market code; fee in the shop's currency.
            'intl_shipping' => ['sometimes', 'nullable', 'array'],
            'intl_shipping.*.fee_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'intl_shipping.*.transit_min_days' => ['required', 'integer', 'min:1', 'max:90'],
            'intl_shipping.*.transit_max_days' => ['required', 'integer', 'min:1', 'max:90', 'gte:intl_shipping.*.transit_min_days'],
            // Optional customs / export paperwork charge for that country, shown on the buyer's bill.
            'intl_shipping.*.paperwork_fee_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            // Own delivery (local): the seller's own delivery person, within a radius of a ship-from address. null = off.
            'local_delivery' => ['sometimes', 'nullable', 'array'],
            'local_delivery.address_id' => ['required_with:local_delivery', 'integer', Rule::exists('shop_addresses', 'id')->where('shop_id', $shop->id)],
            'local_delivery.radius_km' => ['required_with:local_delivery', 'numeric', 'min:1', 'max:100'],
            'local_delivery.fee_cents' => ['required_with:local_delivery', 'integer', 'min:0', 'max:100000000'],
            'local_delivery.days' => ['required_with:local_delivery', 'integer', 'min:1', 'max:7'],
            // The store's exact map point, if the seller sets it (else found from the address).
            'local_delivery.lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:local_delivery.lng'],
            'local_delivery.lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:local_delivery.lat'],
        ]);

        if (array_key_exists('local_delivery', $data)) {
            if ($data['local_delivery']) {
                abort_unless(SellerShipping::localDeliveryOffered(), 422, 'Local delivery isn\'t offered right now.');
                abort_if(\App\Support\SellerStores::blockedByAdmin($shop), 422, \App\Support\Branding::name().' has switched off local delivery for your store — message us to turn it back on.');
                abort_if((float) $data['local_delivery']['radius_km'] > SellerShipping::localMaxKm(), 422, 'Local delivery can cover up to '.SellerShipping::localMaxKm().' km.');
                abort_unless(in_array($data['fulfillment_mode'] ?? $shop->fulfillment_mode, ['self', 'label'], true), 422, 'Local delivery is for sellers who ship orders themselves — choose how you ship first.');
                $address = $shop->addresses()->findOrFail($data['local_delivery']['address_id']);
                $point = isset($data['local_delivery']['lat'], $data['local_delivery']['lng'])
                    ? [(float) $data['local_delivery']['lat'], (float) $data['local_delivery']['lng']]
                    : $this->pointFor($address);
                abort_unless($point, 422, 'We couldn\'t find that address on the map — check it, then try again.');
                $shop->local_delivery = [
                    'address_id' => $address->id,
                    'radius_km' => round((float) $data['local_delivery']['radius_km'], 1),
                    'fee_cents' => (int) $data['local_delivery']['fee_cents'],
                    'days' => (int) $data['local_delivery']['days'],
                    'lat' => $point[0],
                    'lng' => $point[1],
                    'pin_set' => isset($data['local_delivery']['lat']),
                ];
            } elseif ($shop->local_delivery) {
                // Turning it off is checked first (popup) and, with riders linked, waits for admin.
                abort_unless($request->boolean('confirm_off'), 422, 'Confirm turning off local delivery.');
                $status = \App\Support\SellerStores::sellerTurnsOff($shop, $request->user());
                $shop->refresh();
                if ($status === 'off_requested') {
                    return response()->json(['data' => $this->payload($shop), 'message' => 'Sent to '.\App\Support\Branding::name().' to confirm — local delivery stays on until then.']);
                }
            }
        }

        if (array_key_exists('intl_shipping', $data)) {
            $allowed = collect($this->intlDestinations($shop))->pluck('code')->all();
            $intl = collect((array) $data['intl_shipping'])
                ->mapWithKeys(fn ($terms, $code) => [strtoupper((string) $code) => $terms]);
            abort_if($intl->keys()->diff($allowed)->isNotEmpty(), 422, 'You can only ship to the countries '.\App\Support\Branding::name().' sells in.');
            if ($intl->isNotEmpty()) {
                abort_if($block = $this->intlBlocked($shop), 422, (string) $block);
                abort_unless(\App\Support\SellerIntl::allowed($shop), 422, 'Apply for international selling first — '.\App\Support\Branding::name().' approves it before you can ship abroad.');
                \App\Support\SellerPolicies::assertAccepted($shop->seller, 'international');
                abort_unless($shop->fulfillment_mode === 'self' || ($data['fulfillment_mode'] ?? null) === 'self', 422, 'Shipping abroad needs "I ship with my own courier" — switch to it first.');
            }
            $shop->intl_shipping = $intl->isEmpty() ? null : $intl->map(fn ($t) => [
                'fee_cents' => (int) $t['fee_cents'],
                'transit_min_days' => (int) $t['transit_min_days'],
                'transit_max_days' => (int) $t['transit_max_days'],
            ] + (! empty($t['paperwork_fee_cents']) ? ['paperwork_fee_cents' => (int) $t['paperwork_fee_cents']] : []))->all();
        }

        if (! empty($data['accept_free_shipping'])) {
            $shop->free_shipping_accepted_at ??= now();
        }
        if (! empty($data['accepts_cod'])) {
            abort_if($why = SellerCod::sellerReason($shop), 422, (string) $why);
            abort_unless(in_array($data['fulfillment_mode'] ?? $shop->fulfillment_mode, ['self', 'label'], true), 422, 'Cash on delivery is for orders you ship yourself — choose how you ship first.');
        }
        foreach (['ships_saturday', 'ships_sunday', 'working_holidays', 'accepts_cod'] as $key) {
            if (array_key_exists($key, $data)) {
                $shop->{$key} = $data[$key];
            }
        }
        if (($data['fulfillment_mode'] ?? null) === 'nextech' && $shop->fulfillment_mode !== 'nextech') {
            abort_unless(SellerShipping::nextechPickup() === 'available', 422, \App\Support\Branding::name().' pickup isn\'t offered right now — ship orders yourself (own courier or a '.\App\Support\Branding::name().' label).');
        }
        if (isset($data['fulfillment_mode']) && $data['fulfillment_mode'] !== 'nextech') {
            abort_unless(SellerShipping::setupComplete($shop), 422, 'Before shipping orders yourself, add a ship-from address, create a shipping template, and accept the free-shipping rule.');
        }
        if (isset($data['fulfillment_mode'])) {
            $shop->fulfillment_mode = $data['fulfillment_mode'];
        }
        $shop->save();

        return response()->json(['data' => $this->payload($shop->fresh())]);
    }

    /**
     * Apply to sell abroad: export ID, export document and a signed
     * declaration (typed name + date). Admin approves it in Sellers.
     */
    public function applyInternational(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless(\App\Support\SellerIntl::requiresApproval() && ! $shop->is_house, 422, 'No approval is needed — set up the countries you ship to.');
        abort_if(\App\Support\SellerIntl::status($shop) === 'approved', 422, 'You\'re already approved to sell abroad.');
        abort_if($block = $this->intlBlocked($shop), 422, (string) $block);
        \App\Support\SellerPolicies::assertAccepted($shop->seller, 'international');
        $rules = \App\Support\SellerIntl::rules($shop->market);
        $userId = $request->user()->id;
        $data = $request->validate([
            'export_id' => array_values(array_filter(['required', 'string', 'max:40', $rules['id_regex'] ? 'regex:/'.$rules['id_regex'].'/i' : null])),
            'document_path' => [$rules['document_required'] ? 'required' : 'nullable', 'string', 'max:255', 'starts_with:kyc/'.$userId.'/'],
            'agree' => ['required', 'accepted'],
            'signed_name' => ['required', 'string', 'min:3', 'max:160'],
        ], ['export_id.regex' => 'That doesn\'t look like a valid '.$rules['id_label'].'.', 'agree.accepted' => 'Tick that you accept the declaration.']);

        abort_if(\App\Support\SellerIntl::status($shop) === 'revoked', 422, \App\Support\Branding::name().' stopped your international selling — contact us in Messages.');
        $shop->intl_approval = [
            // The seller's signature approves it — no admin step.
            'status' => 'approved',
            'export_id' => strtoupper(trim($data['export_id'])),
            'document_path' => $data['document_path'] ?? null,
            'declaration' => \App\Support\SellerIntl::declaration(Country::find($shop->market)['name'] ?? $shop->market),
            'signed_name' => trim($data['signed_name']),
            'signed_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
        ];
        $shop->save();
        try {
            \Illuminate\Support\Facades\Notification::send(\App\Models\User::where('is_admin', true)->get(), new \App\Notifications\AdminSellerSubmitted($shop->seller, 'international selling terms (approved on signing — you can stop it in Sellers)'));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->payload($shop->fresh())]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $this->validateAddress($request);

        $address = DB::transaction(function () use ($shop, $data) {
            $first = ! $shop->addresses()->exists();
            if (($data['is_default'] ?? false) || $first) {
                $shop->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            return $shop->addresses()->create($data);
        });

        return response()->json(['data' => $address], 201);
    }

    public function updateAddress(Request $request, ShopAddress $address): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($address->shop_id === $shop->id, 404);
        $data = $this->validateAddress($request, partial: true);

        DB::transaction(function () use ($shop, $address, $data) {
            if ($data['is_default'] ?? false) {
                $shop->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update($data);
        });
        // Own delivery measures from this address: keep its map point current.
        if ((int) ($shop->local_delivery['address_id'] ?? 0) === $address->id && ($point = $this->pointFor($address->fresh()))) {
            $shop->update(['local_delivery' => ['lat' => $point[0], 'lng' => $point[1]] + (array) $shop->local_delivery]);
        }

        return response()->json(['data' => $address->fresh()]);
    }

    public function destroyAddress(Request $request, ShopAddress $address): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($address->shop_id === $shop->id, 404);
        abort_if($shop->shipsItself() && $shop->addresses()->count() === 1, 422, 'You need at least one ship-from address while you ship orders yourself.');

        $wasDefault = $address->is_default;
        $address->delete();
        // Own delivery measured from this address switches off; the seller picks another.
        if ((int) ($shop->local_delivery['address_id'] ?? 0) === $address->id) {
            $shop->update(['local_delivery' => null]);
        }
        if ($wasDefault) {
            $shop->addresses()->orderBy('id')->first()?->update(['is_default' => true]);
        }

        return response()->json(status: 204);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $template = $this->saveTemplate($shop, new ShippingTemplate(['shop_id' => $shop->id]), $this->validateTemplate($request, $shop));

        return response()->json(['data' => $template], 201);
    }

    public function updateTemplate(Request $request, ShippingTemplate $template): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($template->shop_id === $shop->id, 404);

        return response()->json(['data' => $this->saveTemplate($shop, $template, $this->validateTemplate($request, $shop))]);
    }

    public function destroyTemplate(Request $request, ShippingTemplate $template): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($template->shop_id === $shop->id, 404);
        abort_if($shop->shipsItself() && $shop->shippingTemplates()->count() === 1, 422, 'You need at least one shipping template while you ship orders yourself.');

        $wasDefault = $template->is_default;
        $template->delete(); // products using it fall back to the default template
        if ($wasDefault) {
            $shop->shippingTemplates()->orderBy('id')->first()?->update(['is_default' => true]);
        }

        return response()->json(status: 204);
    }

    // ---------------------------------------------------------------- helpers

    /** @param  array<string, mixed>  $data */
    private function saveTemplate(Shop $shop, ShippingTemplate $template, array $data): ShippingTemplate
    {
        return DB::transaction(function () use ($shop, $template, $data) {
            $makeDefault = ($data['is_default'] ?? false) || ! $shop->shippingTemplates()->whereKeyNot($template->id ?? 0)->exists();
            if ($makeDefault) {
                $shop->shippingTemplates()->whereKeyNot($template->id ?? 0)->update(['is_default' => false]);
            }

            $template->fill([
                'name' => $data['name'],
                'product_type' => $data['product_type'] ?? 'standard',
                'shop_address_id' => $data['shop_address_id'],
                'handling_days' => $data['handling_days'] ?? 1,
                'is_default' => $makeDefault,
            ])->save();

            $template->groups()->delete();
            foreach (array_values($data['groups']) as $i => $group) {
                $template->groups()->create([
                    'regions' => array_values(array_unique($group['regions'])),
                    'address_types' => array_values(array_unique($group['address_types'] ?? ['standard'])),
                    'transit_min_days' => $group['transit_min_days'],
                    'transit_max_days' => $group['transit_max_days'],
                    'fee_cents' => $group['fee_cents'],
                    'sort_order' => $i,
                ]);
            }

            return $template->fresh(['groups', 'address']);
        });
    }

    /** @return array<string, mixed> */
    private function validateTemplate(Request $request, Shop $shop): array
    {
        $states = Market::states($shop->market);
        $regions = array_merge(array_keys($states), ['ALL']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_type' => ['sometimes', Rule::in(['standard', 'oversized', 'fragile'])],
            'shop_address_id' => ['required', 'integer', Rule::exists('shop_addresses', 'id')->where('shop_id', $shop->id)],
            'handling_days' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_default' => ['sometimes', 'boolean'],
            'groups' => ['required', 'array', 'min:1', 'max:20'],
            'groups.*.regions' => ['required', 'array', 'min:1'],
            'groups.*.regions.*' => ['string', Rule::in($regions)],
            'groups.*.address_types' => ['sometimes', 'array', 'min:1'],
            'groups.*.address_types.*' => ['string', Rule::in(array_keys(SellerShipping::addressTypes($shop->market)))],
            'groups.*.transit_min_days' => ['required', 'integer', 'min:1', 'max:30'],
            'groups.*.transit_max_days' => ['required', 'integer', 'min:1', 'max:30'],
            'groups.*.fee_cents' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        // A state may sit in only one group, so a quote is never ambiguous.
        $seen = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['regions'] as $region) {
                abort_if(isset($seen[$region]), 422, ($region === 'ALL' ? 'Only one group can cover "all other states"' : $states[$region].' is in more than one group').'.');
                $seen[$region] = true;
            }
        }
        foreach ($data['groups'] as $group) {
            abort_if($group['transit_max_days'] < $group['transit_min_days'], 422, 'Transit time "to" must be at least "from".');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateAddress(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $market = $this->shop($request)->market;
        $postal = Country::find($market)['address']['postal_regex'] ?? '^\d{5}(-\d{4})?$';

        $data = $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'line1' => [$req, 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => [$req, 'string', 'max:100'],
            'state' => [$req, 'string', Rule::in(array_keys(Market::states($market)))],
            'postal_code' => [$req, 'string', 'regex:/'.$postal.'/'],
            'phone' => [$req, 'string', 'max:32'],
            'contact_name' => [$req, 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $data['country'] = $market;

        return $data;
    }

    /** Ask the store's admin for a day off for this store (they add it, or decline). */
    public function requestHoliday(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'name' => ['required', 'string', 'max:60'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        \App\Support\ExtraHolidays::request($shop, $data['date'], $data['name'], $data['reason'] ?? null);
        try {
            \Illuminate\Support\Facades\Notification::send(\App\Models\User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice("Day off requested — {$shop->name}", "{$shop->name} asks for {$data['date']} ({$data['name']}) as a day off".($data['reason'] ?? '' ? ": {$data['reason']}" : '').'. Decide it under Shipping → Holidays.'));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->payload($shop)]);
    }

    /** @return array<string, mixed> */
    private function payload(Shop $shop): array
    {
        $today = now();
        $names = SellerShipping::holidayNames($shop->market);
        $upcoming = collect(SellerShipping::holidayDates($today->year, $shop->market) + SellerShipping::holidayDates($today->year + 1, $shop->market))
            ->filter(fn ($key, $date) => $date >= $today->toDateString())
            ->unique()
            ->map(fn ($key, $date) => ['key' => $key, 'name' => $names[$key] ?? $key, 'date' => $date])
            ->values();

        return [
            'fulfillment_mode' => $shop->fulfillment_mode,
            'accepts_cod' => (bool) $shop->accepts_cod,
            'cod' => SellerCod::status($shop),
            'ships_saturday' => (bool) $shop->ships_saturday,
            'ships_sunday' => (bool) $shop->ships_sunday,
            'working_holidays' => $shop->working_holidays ?? [],
            'free_shipping_accepted_at' => $shop->free_shipping_accepted_at,
            'free_shipping_threshold_cents' => SellerShipping::freeShippingThresholdCents($shop->market),
            'market' => $shop->market,
            'country_name' => Country::find($shop->market)['name'] ?? $shop->market,
            'currency' => Market::currency($shop->market),
            'postal_label' => Country::find($shop->market)['address']['postal_label'] ?? 'ZIP code',
            'address_types' => SellerShipping::addressTypes($shop->market),
            'setup_complete' => SellerShipping::setupComplete($shop),
            'nextech_pickup' => SellerShipping::nextechPickup(),
            // Whether this seller must set up shipping before adding products.
            'shipping_required' => \App\Support\SellerRequirements::on($shop, 'shipping_setup'),
            'label_mode' => SellerFulfillment::labelMode(),
            'label_templates' => LabelTemplate::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'size', 'is_default']),
            'label_template_id' => $shop->label_template_id,
            'addresses' => $shop->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'templates' => $shop->shippingTemplates()->with(['groups', 'address'])->orderByDesc('is_default')->orderBy('id')->get(),
            'states' => Market::states($shop->market),
            'holidays' => $upcoming,
            // The store's own days off (added by admin on request), and requests still waiting.
            'store_days_off' => collect(\App\Support\ExtraHolidays::storeDays($shop))->filter(fn ($n, $d) => $d >= $today->toDateString())->map(fn ($name, $date) => ['date' => $date, 'name' => $name])->values(),
            'holiday_requests' => collect(\App\Support\ExtraHolidays::requests())->where('shop_id', $shop->id)->values(),
            'carriers' => collect(Market::carriers($shop->market))->map(fn ($c, $key) => ['value' => $key, 'label' => $c[0]])->values(),
            // International shipping: countries admin has switched on, other than the shop's own.
            'intl_shipping' => (object) ((array) $shop->intl_shipping),
            'intl_destinations' => $this->intlDestinations($shop),
            'intl_blocked' => $this->intlBlocked($shop),
            // Approval to sell abroad: the seller's application and admin's decision.
            'intl_requires_approval' => \App\Support\SellerIntl::requiresApproval() && ! $shop->is_house,
            'intl_approval' => $shop->intl_approval ? collect($shop->intl_approval)->except(['ip'])->all() : null,
            'intl_rules' => \App\Support\SellerIntl::rules($shop->market),
            'intl_declaration' => \App\Support\SellerIntl::declaration(Country::find($shop->market)['name'] ?? $shop->market),
            'intl_policies' => array_values(array_filter(\App\Support\SellerPolicies::status($shop->seller), fn ($p) => $p['for'] === 'international')),
            'local_delivery_offered' => SellerShipping::localDeliveryOffered(),
            'local_max_km' => SellerShipping::localMaxKm(),
            'local_delivery' => $shop->local_delivery ? collect($shop->local_delivery)->except(! empty($shop->local_delivery['pin_set']) ? [] : ['lat', 'lng'])->all() : null,
            // Its store in Stores / hubs, and whether admin has switched local delivery off.
            'local_store' => ($store = \App\Models\Store::query()->where('shop_id', $shop->id)->withCount('riders')->first())
                ? ['id' => $store->id, 'active' => $store->is_active, 'blocked' => ! $store->local_delivery_active, 'status' => \App\Support\SellerStores::status($store), 'off_requested_at' => $store->local_delivery_off_requested_at, 'riders_count' => $store->riders_count] : null,
        ];
    }

    /** @return array{0: float, 1: float}|null */
    private function pointFor(ShopAddress $address): ?array
    {
        return SellerShipping::addressPoint($address->only(['line1', 'city', 'state', 'postal_code']));
    }

    /** @return list<array{code: string, name: string, currency: string}> */
    private function intlDestinations(Shop $shop): array
    {
        return collect(Market::codes())
            ->reject(fn ($code) => $code === $shop->market)
            ->map(fn ($code) => ['code' => $code, 'name' => Country::find($code)['name'] ?? $code, 'currency' => Market::currency($code)])
            ->values()->all();
    }

    /** Why this shop can't ship abroad, or null. */
    private function intlBlocked(Shop $shop): ?string
    {
        if (SellerTax::onlyState($shop->seller)) {
            return 'Sellers registered with a PAN only (no GSTIN) can sell only within their own state, so they can\'t ship abroad.';
        }

        return null;
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');
        abort_unless($seller->shop, 404, 'No shop found for this seller.');

        return $seller->shop;
    }
}
