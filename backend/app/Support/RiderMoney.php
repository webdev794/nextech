<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\RiderLedgerEntry;
use App\Models\SellerLedgerEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Riders' money across the store's own and sellers' stores:
 *  - a seller's rider is paid per delivery (the seller's rate); the store pays
 *    the rider and charges the seller;
 *  - cash a rider still holds for a seller is taken from the rider's earnings
 *    and given to the seller (month end, or when admin settles) — their earnings
 *    are the seller's only cover;
 *  - the money table per rider and month, for admin (all) and sellers (theirs).
 */
class RiderMoney
{
    /** A seller's pay per delivery for their riders (their rate, else the store's own base pay). */
    public static function sellerRateCents(Store $store): int
    {
        return (int) ($store->rider_pay_cents ?? RiderLedger::baseCents($store->country));
    }

    /** A seller's rider delivered a package: credit the rider, charge the seller. Once per package. */
    public static function creditSellerDelivery(OrderPackage $package): void
    {
        if (! $package->rider_id || $package->status !== 'delivered') {
            return;
        }
        $store = SellerStores::ensure($package->shop);
        $pay = self::sellerRateCents($store);
        DB::transaction(function () use ($package, $store, $pay): void {
            $note = "Package {$package->id} — ".($package->shop?->name ?? 'seller');
            $done = RiderLedgerEntry::query()->where('user_id', $package->rider_id)->where('type', 'seller_delivery')->where('note', $note)->lockForUpdate()->exists();
            if ($done || $pay <= 0) {
                return;
            }
            RiderLedgerEntry::create(['user_id' => $package->rider_id, 'order_id' => $package->order_id, 'type' => 'seller_delivery', 'amount_cents' => $pay, 'note' => $note]);
            SellerLedgerEntry::create(['shop_id' => $package->shop_id, 'order_id' => null, 'type' => 'rider_pay', 'amount_cents' => -$pay, 'note' => "Rider pay — order #{$package->order_id}"]);
        });
    }

    /**
     * Cash a rider still holds for sellers is taken from their earnings and given
     * to the sellers — whole deliveries, oldest first, while their earnings cover
     * it. Returns how much was moved.
     */
    public static function offsetSellerCash(User $rider, ?User $by = null): int
    {
        $moved = 0;
        // Not cash the rider says they handed over (waiting for the seller to confirm).
        $packages = OrderPackage::query()->where('rider_id', $rider->id)->whereNotNull('cash_collected_at')->whereNull('cash_handed_over_at')->whereNull('cash_handover_claimed_at')
            ->with(['order:id,total_cents,payment_method', 'shop:id,name'])->orderBy('cash_collected_at')->get();
        foreach ($packages as $package) {
            $cash = SellerRiders::cashCents($package);
            if ($cash <= 0 || RiderLedger::balanceCents($rider) < $cash) {
                continue;
            }
            DB::transaction(function () use ($rider, $package, $cash, $by): void {
                RiderLedgerEntry::create(['user_id' => $rider->id, 'order_id' => $package->order_id, 'type' => 'cash_offset', 'amount_cents' => -$cash, 'note' => 'Cash kept for '.($package->shop?->name ?? 'a seller')." (order #{$package->order_id}) — taken from earnings", 'created_by' => $by?->id]);
                SellerLedgerEntry::create(['shop_id' => $package->shop_id, 'order_id' => null, 'type' => 'rider_cash_recovered', 'amount_cents' => $cash, 'note' => "Cash {$rider->name} kept (order #{$package->order_id}) — from their earnings", 'created_by' => $by?->id]);
                $package->forceFill(['cash_handed_over_at' => now()])->save();
            });
            $moved += $cash;
        }
        // Nothing left to hand over for a store: no longer paused there.
        foreach ($rider->stores()->whereNotNull('shop_id')->get() as $store) {
            if ($store->pivot?->cash_paused_at && SellerRiderCash::heldCents($rider, $store) === 0) {
                DB::table('rider_store')->where('user_id', $rider->id)->where('store_id', $store->id)->update(['cash_paused_at' => null, 'cash_later_at' => null]);
            }
        }

        return $moved;
    }

    /** All cash a rider holds: the store's own cash on delivery and sellers'. */
    public static function cashHeldCents(User $rider): int
    {
        $sellers = (int) OrderPackage::query()->where('rider_id', $rider->id)->whereNotNull('cash_collected_at')->whereNull('cash_handed_over_at')
            ->with('order:id,total_cents,payment_method')->get()->sum(fn ($p) => SellerRiders::cashCents($p));

        return $rider->codHoldingCents() + $sellers;
    }

    /**
     * A leaving (or any) rider's final settlement: earnings minus all cash they hold.
     * Positive = to pay them; negative = they owe the store / sellers.
     * Paid 7 days after their last delivery (only delivery claims involve the rider).
     */
    public static function settlement(User $rider): array
    {
        $earned = RiderLedger::balanceCents($rider);
        $cash = self::cashHeldCents($rider);
        $last = collect([
            Order::query()->where('delivery_partner_id', $rider->id)->max('delivered_at'),
            OrderPackage::query()->where('rider_id', $rider->id)->max('delivered_at'),
        ])->filter()->map(fn ($d) => Carbon::parse($d))->max();

        return [
            'earned_cents' => $earned,
            'cash_held_cents' => $cash,
            'net_cents' => $earned - $cash,
            'last_delivery_at' => $last,
            'settle_on' => ($last ?? now())->copy()->addDays(7)->toDateString(),
            'currency' => Market::currency(RiderLedger::marketFor($rider)),
        ];
    }

    /**
     * The money table for a month: per rider, deliveries, delivery fees buyers paid,
     * what the rider earned, cash collected / handed over / still held, and their balance.
     * $store limits it to one seller's store (what that seller sees).
     *
     * @return list<array<string, mixed>>
     */
    public static function table(Carbon $month, ?Store $store = null, ?string $market = null): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $riders = User::query()->where('is_rider', true)
            ->when($store, fn ($q) => $q->whereHas('stores', fn ($s) => $s->whereKey($store->id)))
            ->when(! $store && $market, fn ($q) => $q->whereHas('stores', fn ($s) => $s->where('country', $market)))
            ->orderBy('name')->get();

        return $riders->map(function (User $rider) use ($from, $to, $store) {
            $packages = OrderPackage::query()->where('rider_id', $rider->id)->where('status', 'delivered')->whereBetween('delivered_at', [$from, $to])
                ->when($store, fn ($q) => $q->where('shop_id', $store->shop_id))
                ->with(['order:id,total_cents,payment_method', 'order.shopShipping'])->get();
            $ownOrders = $store ? collect() : Order::query()->where('delivery_partner_id', $rider->id)->whereBetween('delivered_at', [$from, $to])->get(['id', 'delivery_fee_cents', 'total_cents', 'payment_method']);
            $credits = RiderLedgerEntry::query()->where('user_id', $rider->id)->whereBetween('created_at', [$from, $to])
                ->whereIn('type', $store ? ['seller_delivery', 'bonus'] : ['delivery_credit', 'seller_delivery', 'adjustment', 'bonus', 'monthly_pay', 'top_up'])
                ->when($store, fn ($q) => $q->where('note', 'like', '% — '.$store->shop?->name))->sum('amount_cents');
            $feesFromBuyers = (int) $packages->sum(fn ($p) => (int) ($p->order?->shopShipping->firstWhere('shop_id', $p->shop_id)?->fee_cents ?? 0))
                + (int) $ownOrders->sum('delivery_fee_cents');
            $cashCollected = (int) $packages->filter(fn ($p) => $p->cash_collected_at)->sum(fn ($p) => SellerRiders::cashCents($p))
                + (int) $ownOrders->where('payment_method', 'cod')->sum('total_cents');

            return [
                'rider_id' => $rider->id,
                'name' => $rider->name,
                'active' => (bool) $rider->rider_is_active,
                'deliveries' => $packages->count() + $ownOrders->count(),
                'fees_from_buyers_cents' => $feesFromBuyers,
                'earned_cents' => (int) $credits,
                // Did their deliveries earn more in fees than they were paid? (worth it for the store / seller)
                'margin_cents' => $feesFromBuyers - (int) $credits,
                'cash_collected_cents' => $cashCollected,
                'cash_held_cents' => $store ? SellerRiderCash::heldCents($rider, $store) : self::cashHeldCents($rider),
                // Admin only: their whole balance (sellers see just their store's figures).
                'balance_cents' => $store ? null : RiderLedger::balanceCents($rider),
                'leaving_on' => $rider->rider_notice_at && ! $rider->rider_notice_processed_at ? $rider->rider_leaving_on?->toDateString() : null,
            ];
        })->values()->all();
    }
}
