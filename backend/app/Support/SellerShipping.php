<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Shop;
use App\Models\Setting;
use App\Models\ShippingTemplate;
use Illuminate\Support\Carbon;

/**
 * Seller-shipped fulfillment (Temu-style): shipping-template quotes, the
 * working-day calendar used for ship-by / delivery estimates, and — per
 * market (config/markets.php) — the states, holidays and carriers sellers can
 * ship with (tracking-number format + tracking link).
 */
class SellerShipping
{
    public const MODES = ['nextech', 'self', 'label'];

    /** Carrier key for packages the seller delivers themselves (Own delivery). */
    public const LOCAL = 'Local';

    /**
     * Whether sellers may pick "NexTech collects & delivers": 'available',
     * 'disabled' (shown but can't be chosen) or 'hidden'. Admin turns it off
     * to push sellers to ship their own orders.
     */
    public static function nextechPickup(): string
    {
        $value = (string) Setting::get('nextech_pickup', 'available');

        return in_array($value, ['available', 'disabled', 'hidden'], true) ? $value : 'available';
    }

    public static function freeShippingThresholdCents(?string $market = null): int
    {
        return (int) CheckoutFees::current($market)['free_delivery_threshold_cents'];
    }

    /** "California" / "ca" / "CA" -> "CA"; null when it isn't a state of that market. */
    public static function stateCode(?string $state, ?string $market = null): ?string
    {
        $state = trim((string) $state);
        if ($state === '') {
            return null;
        }
        $states = Market::states($market);
        $upper = strtoupper($state);
        if (isset($states[$upper])) {
            return $upper;
        }
        $code = array_search(strtolower($state), array_map('strtolower', $states), true);

        return $code === false ? null : $code;
    }

    public static function trackingUrl(?string $carrier, ?string $tracking): ?string
    {
        $prefix = Market::allCarriers()[$carrier][2] ?? null;

        return $prefix && $tracking ? $prefix.rawurlencode($tracking) : null;
    }

    /** True when the number matches the carrier's usual format (a hint, never a hard block). */
    public static function trackingLooksValid(string $carrier, string $tracking): bool
    {
        $pattern = Market::allCarriers()[$carrier][1] ?? null;

        return $pattern === null || (bool) preg_match($pattern, preg_replace('/\s+/', '', $tracking));
    }

    // ---------------------------------------------------------------- calendar

    /** @return array<string, string> date (Y-m-d) => holiday key, for a year in a market. */
    public static function holidayDates(int $year, ?string $market = 'US'): array
    {
        $profile = Market::profile($market);
        if ($profile['code'] === 'IN') {
            $dates = [
                Carbon::create($year, 1, 26)->toDateString() => 'republic',
                Carbon::create($year, 8, 15)->toDateString() => 'independence_in',
                Carbon::create($year, 10, 2)->toDateString() => 'gandhi',
                Carbon::create($year, 12, 25)->toDateString() => 'christmas',
            ];
            foreach ((array) ($profile['festival_dates'] ?? []) as $key => $list) {
                foreach ((array) $list as $date) {
                    if (str_starts_with($date, $year.'-')) {
                        $dates[$date] = $key;
                    }
                }
            }
            ksort($dates);

            return $dates;
        }

        $nth = fn (int $month, int $dow, int $n) => Carbon::create($year, $month, 1)->nthOfMonth($n, $dow);
        $last = fn (int $month, int $dow) => Carbon::create($year, $month, 1)->lastOfMonth($dow);

        return [
            Carbon::create($year, 1, 1)->toDateString() => 'new_year',
            $nth(1, Carbon::MONDAY, 3)->toDateString() => 'mlk',
            $nth(2, Carbon::MONDAY, 3)->toDateString() => 'presidents',
            $last(5, Carbon::MONDAY)->toDateString() => 'memorial',
            Carbon::create($year, 6, 19)->toDateString() => 'juneteenth',
            Carbon::create($year, 7, 4)->toDateString() => 'independence',
            $nth(9, Carbon::MONDAY, 1)->toDateString() => 'labor',
            $nth(10, Carbon::MONDAY, 2)->toDateString() => 'columbus',
            Carbon::create($year, 11, 11)->toDateString() => 'veterans',
            $nth(11, Carbon::THURSDAY, 4)->toDateString() => 'thanksgiving',
            Carbon::create($year, 12, 25)->toDateString() => 'christmas',
        ];
    }

    /**
     * Is this a day the shop works (and so counts towards handling and the
     * delivery estimate)? Weekends and federal holidays are off unless the
     * shop has switched them on — matching Temu's defaults.
     */
    public static function isWorkingDay(Shop $shop, Carbon $day): bool
    {
        if ($day->isSaturday() && ! $shop->ships_saturday) {
            return false;
        }
        if ($day->isSunday() && ! $shop->ships_sunday) {
            return false;
        }
        $holiday = self::holidayDates($day->year, $shop->market)[$day->toDateString()] ?? null;

        return $holiday === null || in_array($holiday, (array) $shop->working_holidays, true);
    }

    public static function addWorkingDays(Shop $shop, Carbon $from, int $days): Carbon
    {
        $day = $from->copy()->startOfDay();
        $added = 0;
        while ($added < $days) {
            $day->addDay();
            if (self::isWorkingDay($shop, $day)) {
                $added++;
            }
        }

        return $day;
    }

    // ------------------------------------------------------------------ quotes

    /** Address types a shipping group can cover; military (APO/FPO/DPO) is US-only. */
    public static function addressTypes(?string $market = null): array
    {
        return strtoupper((string) ($market ?? 'US')) === 'US'
            ? ['standard' => 'Street address', 'po_box' => 'PO box', 'military' => 'Military (APO/FPO/DPO)']
            : ['standard' => 'Street address', 'po_box' => 'PO box'];
    }

    /**
     * What kind of address a delivery address is: a military APO/FPO/DPO
     * address (that city, or the armed-forces "states" AA/AE/AP in the US),
     * a PO box, or a standard street address. Null when there is no street
     * line to judge by.
     *
     * @param  array<string, mixed>  $address
     */
    public static function addressType(array $address): ?string
    {
        $lines = trim(($address['line1'] ?? '').' '.($address['line2'] ?? ''));
        $city = strtoupper(trim((string) ($address['city'] ?? '')));
        $state = strtoupper(trim((string) ($address['state'] ?? '')));
        if ($lines === '') {
            return null;
        }
        if (in_array($city, ['APO', 'FPO', 'DPO'], true) || (in_array($state, ['AA', 'AE', 'AP'], true) && strtoupper((string) ($address['country'] ?? 'US')) === 'US')) {
            return 'military';
        }

        return preg_match('/\b(p\.?\s*o\.?\s*box|post\s+office\s+box)\b/i', $lines) ? 'po_box' : 'standard';
    }


    /** The template a product ships under: its own, else the shop's default. */
    public static function templateFor(Product $product, Shop $shop): ?ShippingTemplate
    {
        $template = $product->shipping_template_id
            ? ShippingTemplate::with('groups')->where('shop_id', $shop->id)->find($product->shipping_template_id)
            : null;

        return $template ?? ShippingTemplate::with('groups')->where('shop_id', $shop->id)->where('is_default', true)->first()
            ?? ShippingTemplate::with('groups')->where('shop_id', $shop->id)->orderBy('id')->first();
    }

    /**
     * Shipping for the seller-shipped lines of a cart, per shop. Each shop
     * charges the highest group fee among its products (waived once that
     * shop's subtotal reaches the platform free-shipping threshold) and
     * promises the slowest transit among them.
     *
     * @param  list<array{product: Product, quantity: int, line_total_cents: int}>  $lines
     * Shops in another country than the buyer ($market) charge their
     * international fee for that country instead (converted into the buyer's
     * currency; never free), with their international transit days.
     *
     * @param  ?string  $addressType  standard | po_box | military (see addressType()); null = not known yet
     * @param  ?string  $market  the buyer's country; null = each shop's own
     * @return array{shops: list<array<string, mixed>>, total_cents: int, unshippable: list<string>}
     */
    public static function quote(array $lines, ?string $state, ?Carbon $orderedAt = null, ?string $addressType = null, ?string $market = null, ?array $address = null): array
    {
        $orderedAt ??= now();
        $byShop = [];
        $unshippable = [];
        // The buyer's location, looked up only when a shop offers own delivery.
        $point = false;
        $buyerPoint = function () use (&$point, $address) {
            if ($point === false) {
                $point = self::addressPoint($address);
            }

            return $point;
        };

        foreach ($lines as $line) {
            $product = $line['product'];
            $shop = $product->relationLoaded('shop') ? $product->shop : $product->shop()->first();
            if (! $shop || ! in_array($shop->fulfillment_mode, ['self', 'label'], true)) {
                continue;
            }

            if ($market && $shop->market !== strtoupper($market)) {
                $terms = $product->crossBorderTerms($market);
                if (! $terms) {
                    $unshippable[] = $product->name;

                    continue;
                }
                $template = self::templateFor($product, $shop);
                $entry = $byShop[$shop->id] ??= [
                    'shop' => $shop, 'subtotal' => 0, 'fee' => 0, 'min' => 0, 'max' => 0, 'handling' => 0,
                ];
                $entry['intl'] = true;
                $entry['subtotal'] += (int) $line['line_total_cents'];
                // The shop's fee for that country once per order, plus any extra
                // per-item charge the seller set on this product (heavy / bulky items).
                $extra = (int) ($product->intl_extra_fee_cents ?? 0) * (int) ($line['quantity'] ?? 1);
                $entry['base_seller_fee'] = max($entry['base_seller_fee'] ?? 0, (int) ($terms['fee_cents'] ?? 0));
                $entry['extra_seller_fee'] = ($entry['extra_seller_fee'] ?? 0) + $extra;
                $entry['seller_fee'] = $entry['base_seller_fee'] + $entry['extra_seller_fee'];
                $entry['fee'] = Fx::convert($entry['seller_fee'], Market::currency($shop->market), Market::currency($market));
                // Customs / export paperwork for that country: once per order, only when the seller set one.
                $entry['seller_paperwork'] = max($entry['seller_paperwork'] ?? 0, (int) ($terms['paperwork_fee_cents'] ?? 0));
                $entry['paperwork'] = Fx::convert($entry['seller_paperwork'], Market::currency($shop->market), Market::currency($market));
                $entry['min'] = max($entry['min'], (int) ($terms['transit_min_days'] ?? 0));
                $entry['max'] = max($entry['max'], (int) ($terms['transit_max_days'] ?? 0));
                $entry['handling'] = max($entry['handling'], (int) ($template?->handling_days ?? 1));
                $byShop[$shop->id] = $entry;

                continue;
            }

            $template = self::templateFor($product, $shop);
            // The seller's own delivery: the whole shop's part goes that way when the buyer is within its radius.
            if ($local = self::localFor($shop, $buyerPoint)) {
                $entry = $byShop[$shop->id] ??= [
                    'shop' => $shop, 'subtotal' => 0, 'fee' => 0, 'min' => 0, 'max' => 0, 'handling' => 0,
                ];
                $entry['local'] = true;
                $entry['subtotal'] += (int) $line['line_total_cents'];
                $entry['fee'] = (int) ($local['fee_cents'] ?? 0);
                $entry['max'] = max($entry['max'], (int) ($local['days'] ?? 1));
                $entry['handling'] = max($entry['handling'], (int) ($template?->handling_days ?? 1));
                $byShop[$shop->id] = $entry;

                continue;
            }
            $group = $template?->groupFor(self::stateCode($state, $shop->market));
            // A group limited to some address types can't ship to the others (e.g. a PO box).
            if ($group && $addressType && $group->address_types && ! in_array($addressType, $group->address_types, true)) {
                $group = null;
            }
            if (! $group) {
                $unshippable[] = $product->name;

                continue;
            }

            $entry = $byShop[$shop->id] ??= [
                'shop' => $shop, 'subtotal' => 0, 'fee' => 0, 'min' => 0, 'max' => 0, 'handling' => 0,
            ];
            $entry['subtotal'] += (int) $line['line_total_cents'];
            $entry['fee'] = max($entry['fee'], (int) $group->fee_cents);
            $entry['min'] = max($entry['min'], (int) $group->transit_min_days);
            $entry['max'] = max($entry['max'], (int) $group->transit_max_days);
            $entry['handling'] = max($entry['handling'], (int) $template->handling_days);
            $byShop[$shop->id] = $entry;
        }

        $shops = [];
        $total = 0;
        foreach ($byShop as $shopId => $e) {
            // The free-shipping rule is domestic only — international shipping is always charged.
            $threshold = empty($e['intl']) ? self::freeShippingThresholdCents($e['shop']->market) : 0;
            $free = $threshold > 0 && $e['subtotal'] >= $threshold;
            $fee = $free ? 0 : $e['fee'];
            $shipBy = self::addWorkingDays($e['shop'], $orderedAt, max(1, $e['handling']));
            $shops[] = [
                'shop_id' => $shopId,
                'shop_name' => $e['shop']->name,
                'ships_from' => ! empty($e['intl']) ? $e['shop']->market : null,
                'mode' => $e['shop']->fulfillment_mode,
                'method' => ! empty($e['local']) ? 'local' : null,
                'fee_cents' => $fee,
                // International: the seller's own fee in their currency (what they're credited).
                'seller_fee_cents' => ! empty($e['intl']) ? (int) $e['seller_fee'] : null,
                'paperwork_cents' => (int) ($e['paperwork'] ?? 0),
                'seller_paperwork_cents' => ! empty($e['intl']) ? (int) ($e['seller_paperwork'] ?? 0) : null,
                'free_shipping' => $free,
                'transit_min_days' => $e['min'],
                'transit_max_days' => $e['max'],
                'ship_by' => $shipBy->toDateString(),
                'deliver_from' => self::addWorkingDays($e['shop'], $shipBy, $e['min'])->toDateString(),
                'deliver_by' => self::addWorkingDays($e['shop'], $shipBy, $e['max'])->toDateString(),
            ];
            $total += $fee + (int) ($e['paperwork'] ?? 0);
        }

        return ['shops' => $shops, 'total_cents' => $total, 'unshippable' => $unshippable];
    }

    /** Whether admin lets sellers offer Own delivery (local): 'available' or 'hidden'. */
    public static function localDeliveryOffered(): bool
    {
        return Setting::get('seller_local_delivery', 'available') !== 'hidden';
    }

    /** The largest own-delivery radius admin allows, in km. */
    public static function localMaxKm(): float
    {
        // Small by default (10 km) so a rider's fuel is worth each delivery.
        return max(1.0, min(50.0, (float) Setting::get('seller_local_max_km', 10)));
    }

    /** A shop's own-delivery terms when on and the buyer is within its radius, else null. */
    public static function localFor(Shop $shop, callable $buyerPoint): ?array
    {
        $local = (array) $shop->local_delivery;
        if (! self::localDeliveryOffered() || ! in_array($shop->fulfillment_mode, ['self', 'label'], true) || empty($local['radius_km']) || ! isset($local['lat'], $local['lng'])) {
            return null;
        }
        // Switched off by the store.
        if (SellerStores::blockedByAdmin($shop)) {
            return null;
        }
        [$lat, $lng] = $buyerPoint() ?? [null, null];
        if ($lat === null || $lng === null) {
            return null;
        }

        $radius = min((float) $local['radius_km'], self::localMaxKm());

        return Geo::haversineKm((float) $lat, (float) $lng, (float) $local['lat'], (float) $local['lng']) <= $radius ? $local : null;
    }

    /** [lat, lng] for an address: the pin the buyer set, else geocoded; null when unknown. */
    public static function addressPoint(?array $address): ?array
    {
        if (! $address) {
            return null;
        }
        if (isset($address['latitude'], $address['longitude'])) {
            return [(float) $address['latitude'], (float) $address['longitude']];
        }
        $query = implode(', ', array_filter([$address['line1'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null]));
        if ($query === '') {
            return null;
        }
        [$lat, $lng] = Geo::geocode($query);

        return $lat !== null && $lng !== null ? [$lat, $lng] : null;
    }

    /** Ready to ship itself: an address, a template with at least one group, and the free-shipping rule accepted. */
    public static function setupComplete(Shop $shop): bool
    {
        return $shop->free_shipping_accepted_at !== null
            && $shop->addresses()->exists()
            && $shop->shippingTemplates()->whereHas('groups')->exists();
    }
}
