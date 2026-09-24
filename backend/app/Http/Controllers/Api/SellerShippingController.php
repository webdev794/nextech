<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use App\Models\ShippingTemplate;
use App\Models\Shop;
use App\Models\ShopAddress;
use App\Support\Country;
use App\Support\Market;
use App\Support\SellerFulfillment;
use App\Support\SellerShipping;
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
            'working_holidays.*' => [Rule::in(array_keys(Market::holidays($shop->market)))],
            'accept_free_shipping' => ['sometimes', 'accepted'],
        ]);

        if (! empty($data['accept_free_shipping'])) {
            $shop->free_shipping_accepted_at ??= now();
        }
        foreach (['ships_saturday', 'ships_sunday', 'working_holidays'] as $key) {
            if (array_key_exists($key, $data)) {
                $shop->{$key} = $data[$key];
            }
        }
        if (($data['fulfillment_mode'] ?? null) === 'nextech' && $shop->fulfillment_mode !== 'nextech') {
            abort_unless(SellerShipping::nextechPickup() === 'available', 422, 'NexTech pickup isn\'t offered right now — ship orders yourself (own courier or a NexTech label).');
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

        return response()->json(['data' => $address->fresh()]);
    }

    public function destroyAddress(Request $request, ShopAddress $address): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($address->shop_id === $shop->id, 404);
        abort_if($shop->shipsItself() && $shop->addresses()->count() === 1, 422, 'You need at least one ship-from address while you ship orders yourself.');

        $wasDefault = $address->is_default;
        $address->delete();
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

    /** @return array<string, mixed> */
    private function payload(Shop $shop): array
    {
        $today = now();
        $names = Market::holidays($shop->market);
        $upcoming = collect(SellerShipping::holidayDates($today->year, $shop->market) + SellerShipping::holidayDates($today->year + 1, $shop->market))
            ->filter(fn ($key, $date) => $date >= $today->toDateString())
            ->unique()
            ->map(fn ($key, $date) => ['key' => $key, 'name' => $names[$key] ?? $key, 'date' => $date])
            ->values();

        return [
            'fulfillment_mode' => $shop->fulfillment_mode,
            'ships_saturday' => (bool) $shop->ships_saturday,
            'ships_sunday' => (bool) $shop->ships_sunday,
            'working_holidays' => $shop->working_holidays ?? [],
            'free_shipping_accepted_at' => $shop->free_shipping_accepted_at,
            'free_shipping_threshold_cents' => SellerShipping::freeShippingThresholdCents($shop->market),
            'market' => $shop->market,
            'currency' => Market::currency($shop->market),
            'postal_label' => Country::find($shop->market)['address']['postal_label'] ?? 'ZIP code',
            'setup_complete' => SellerShipping::setupComplete($shop),
            'nextech_pickup' => SellerShipping::nextechPickup(),
            'label_mode' => SellerFulfillment::labelMode(),
            'label_templates' => LabelTemplate::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'size', 'is_default']),
            'label_template_id' => $shop->label_template_id,
            'addresses' => $shop->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'templates' => $shop->shippingTemplates()->with(['groups', 'address'])->orderByDesc('is_default')->orderBy('id')->get(),
            'states' => Market::states($shop->market),
            'holidays' => $upcoming,
            'carriers' => collect(Market::carriers($shop->market))->map(fn ($c, $key) => ['value' => $key, 'label' => $c[0]])->values(),
        ];
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');
        abort_unless($seller->shop, 404, 'No shop found for this seller.');

        return $seller->shop;
    }
}
