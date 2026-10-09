<?php

namespace App\Support;

use App\Models\Order;
use App\Models\RiderLedgerEntry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rider pay: a base fee + per-mile rate credited per completed delivery, and
 * manual payouts recorded by an admin — the rider-side twin of SellerLedger.
 * Entries are append-only; a rider's balance is always SUM(amount_cents).
 *
 * What a rider is actually owed is that balance minus any COD cash they are
 * still holding (collected from customers, not yet handed back to a store),
 * so a payout never sends money to someone who is sitting on the platform's
 * cash.
 */
class RiderLedger
{
    private const KM_PER_MILE = 1.609344;

    /** A rider pay setting in a market's currency (US: the original keys; others: "rider_pay_<CODE>"). */
    public static function marketPay(string $key, ?string $market): int
    {
        $market = $market === null ? Market::home() : strtoupper($market);
        $defaults = Market::profile($market)['rider_pay'] ?? null;
        if (is_array($defaults)) {
            $override = Setting::get('rider_pay_'.$market, []);

            return (int) ((is_array($override) ? $override : [])[$key] ?? $defaults[$key] ?? 0);
        }

        return (int) match ($key) {
            'base_cents' => Setting::get('rider_base_pay_cents', config('rider_pay.base_cents')),
            'per_mile_cents' => Setting::get('rider_per_mile_cents', config('rider_pay.per_mile_cents')),
            'min_payout_cents' => Setting::get('rider_min_payout_cents', config('rider_pay.min_payout_cents')),
            'max_payout_cents' => Setting::get('rider_max_payout_cents', config('rider_pay.max_payout_cents')),
        };
    }

    /** The market a rider works in: their (first) store's country. */
    /**
     * How a rider may be paid in their country: the methods admin allows
     * (Secure access → Withdrawal fees), of those riders can use (bank, PayPal).
     *
     * @return list<string>
     */
    public static function allowedPayoutMethods(User $rider): array
    {
        $fees = SellerPayouts::fees(self::marketFor($rider));
        $allowed = array_values(array_filter(['bank', 'paypal'], fn ($m) => (bool) ($fees[$m]['enabled'] ?? false)));

        return $allowed ?: ['bank'];
    }

    /**
     * Payday check (25th): riders with pay owed but no allowed payout method are told,
     * and so are the sellers they work for (to remind them). Returns how many.
     */
    public static function remindMissingPayoutMethods(): int
    {
        $n = 0;
        foreach (User::query()->where('is_rider', true)->get() as $rider) {
            if (self::balanceCents($rider) <= 0 || ! self::payoutBlocker($rider)) {
                continue;
            }
            $owed = Money::format(self::balanceCents($rider), Market::currency(self::marketFor($rider)));
            try {
                $rider->notify(new \App\Notifications\RiderNotice('Add how you want to be paid', "You have {$owed} to be paid, but no payout method yet. ".self::payoutBlocker($rider).' Your pay can’t be sent until you do.'));
            } catch (\Throwable $e) {
                report($e);
            }
            foreach ($rider->stores()->whereNotNull('shop_id')->with('shop.seller')->get() as $store) {
                if ($store->shop?->seller) {
                    SellerNotify::send($store->shop->seller, $store->shop->seller->user, 'Rider without a payout method', "{$rider->name} has {$owed} to be paid but hasn’t added how they want to be paid. Please ask them to add it in the Rider app (Earnings → Payout method).");
                }
            }
            $n++;
        }

        return $n;
    }

    /** Why the rider can't start work yet for lack of a payout method, or null. */
    public static function payoutBlocker(User $rider): ?string
    {
        return in_array($rider->rider_payout_method, self::allowedPayoutMethods($rider), true)
            ? null
            : 'Add how you want to be paid first (Earnings → Payout method: '.implode(' or ', array_map(fn ($m) => SellerPayouts::label($m), self::allowedPayoutMethods($rider))).').';
    }

    public static function marketFor(User $rider): string
    {
        return Market::forCountry($rider->stores()->value('country'));
    }

    public static function baseCents(?string $market = null): int
    {
        return self::marketPay('base_cents', $market);
    }

    public static function perMileCents(?string $market = null): int
    {
        return self::marketPay('per_mile_cents', $market);
    }

    public static function minPayoutCents(?string $market = null): int
    {
        return self::marketPay('min_payout_cents', $market);
    }

    public static function maxPayoutCents(?string $market = null): int
    {
        return self::marketPay('max_payout_cents', $market);
    }

    public static function balanceCents(User $rider): int
    {
        return (int) RiderLedgerEntry::where('user_id', $rider->id)->sum('amount_cents');
    }

    /** Earnings minus COD cash still in the rider's hand — never negative. */
    public static function owedCents(User $rider): int
    {
        return max(0, self::balanceCents($rider) - $rider->codHoldingCents());
    }

    /** What a rider can request right now: what they're owed, capped at one payout's max. */
    public static function requestableCents(User $rider): int
    {
        $max = self::maxPayoutCents(self::marketFor($rider));

        return $max > 0 ? min(self::owedCents($rider), $max) : self::owedCents($rider);
    }

    /** Straight-line store -> customer distance in miles, or null when either point is unknown. */
    public static function deliveryMiles(Order $order): ?float
    {
        $store = $order->fulfillingStore();
        $lat = $order->delivery_address['latitude'] ?? null;
        $lng = $order->delivery_address['longitude'] ?? null;

        if (! $store || $store->latitude === null || $store->longitude === null || $lat === null || $lng === null) {
            return null;
        }

        $km = Geo::haversineKm((float) $store->latitude, (float) $store->longitude, (float) $lat, (float) $lng);

        return round($km / self::KM_PER_MILE, 2);
    }

    /**
     * Credit the delivering rider for a completed own-rider order. Idempotent
     * — a no-op if this order already has a delivery credit.
     */
    public static function creditForDelivery(Order $order): void
    {
        if (! $order->delivery_partner_id || $order->status !== 'completed' || $order->usesOnlineCourier()) {
            return;
        }
        // On monthly pay: no per-delivery credit — the delivery counts towards the month.
        if (RiderPayPlan::of(User::find($order->delivery_partner_id) ?? new User)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $exists = RiderLedgerEntry::query()
                ->where('order_id', $order->id)
                ->where('type', 'delivery_credit')
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                return;
            }

            $miles = self::deliveryMiles($order);
            $amount = self::baseCents($order->market) + (int) round(($miles ?? 0) * self::perMileCents($order->market));

            RiderLedgerEntry::create([
                'user_id' => $order->delivery_partner_id,
                'order_id' => $order->id,
                'type' => 'delivery_credit',
                'amount_cents' => $amount,
                'distance_miles' => $miles,
            ]);
        });
    }

    /** A manual settlement recorded by an admin — payouts happen outside the app. */
    public static function recordPayout(User $rider, int $amountCents, ?string $note, User $admin): RiderLedgerEntry
    {
        return RiderLedgerEntry::create([
            'user_id' => $rider->id,
            'order_id' => null,
            'type' => 'payout_debit',
            'amount_cents' => -$amountCents,
            'note' => $note,
            'created_by' => $admin->id,
        ]);
    }
}
