<?php

namespace App\Support;

use App\Models\OrderPackage;
use App\Models\Store;
use App\Models\User;
use App\Notifications\RiderNotice;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cash on delivery collected by a seller's riders. The cash is the seller's: the
 * rider hands it over and the seller marks it received. A rider is paused for that
 * store (not others) when they go over the store's cash limit, or still hold cash
 * the next day — until the seller marks it received. A seller who trusts a rider
 * can choose "Later" (at their own risk: the store isn't responsible for it).
 */
class SellerRiderCash
{
    /** Default cash a rider may hold per store, by country (minor units); sellers can change theirs. */
    public const DEFAULT_LIMITS = ['US' => 20000, 'IN' => 500000];

    /**
     * The largest order cash on delivery is offered for (admin-set per country, at the
     * store's own risk; default the same as a rider's cash limit). A seller can lower
     * it for their own orders with their riders' cash limit.
     */
    public static function codMaxCents(?string $country, ?\App\Models\Shop $sellerShop = null): int
    {
        $saved = (array) Setting::get('cod_max_order', []);
        $country = strtoupper((string) $country);
        $max = (int) ($saved[$country] ?? self::DEFAULT_LIMITS[$country] ?? 20000);
        if ($sellerShop && ($store = Store::query()->where('shop_id', $sellerShop->id)->first()) && $store->rider_cash_limit_cents !== null) {
            $max = min($max, (int) $store->rider_cash_limit_cents);
        }

        return $max;
    }

    /** NexTech's own cash limit per rider for a country (admin-set; settled by admin, not daily). */
    public static function ownLimitCents(?string $country): int
    {
        $saved = (array) Setting::get('rider_cash_limit', []);
        $country = strtoupper((string) $country);

        return (int) ($saved[$country] ?? self::DEFAULT_LIMITS[$country] ?? 20000);
    }

    /**
     * Why this rider can't take orders from any store right now (null = they can):
     * they're paused by a seller for cash, or hold more of the store's own cash than its limit.
     */
    public static function blockedReason(User $rider): ?string
    {
        $paused = $rider->stores()->wherePivotNotNull('cash_paused_at')->first();
        if ($paused) {
            return 'hand over the cash you hold for '.($paused->shop?->name ?? $paused->name).' first';
        }
        $country = $rider->stores()->whereNull('shop_id')->value('country');
        $held = $rider->codHoldingCents();
        if ($held > self::ownLimitCents($country)) {
            return 'return the '.Money::format($held, Market::currency($country)).' of cash on delivery you hold to the store first';
        }

        return null;
    }

    public static function limitCents(Store $store): int
    {
        return (int) ($store->rider_cash_limit_cents ?? self::DEFAULT_LIMITS[strtoupper((string) $store->country)] ?? 20000);
    }

    /** Packages whose cash this rider collected for the store's seller and hasn't handed over. */
    public static function outstanding(User $rider, Store $store)
    {
        return OrderPackage::query()->where('rider_id', $rider->id)->where('shop_id', $store->shop_id)
            ->whereNotNull('cash_collected_at')->whereNull('cash_handed_over_at')->with('order:id,total_cents,payment_method');
    }

    /** Cash the rider still has to hand over: not handed over, and not claimed as handed (waiting for the seller). */
    public static function unclaimed(User $rider, Store $store)
    {
        return self::outstanding($rider, $store)->whereNull('cash_handover_claimed_at');
    }

    /**
     * The rider says they handed the cash over. Only the seller (whose cash it is) confirms
     * it — Cash received or Not received; they're reminded until they answer. Unpauses meanwhile.
     */
    public static function claimHandover(User $rider, Store $store): int
    {
        $count = self::outstanding($rider, $store)->whereNull('cash_handover_claimed_at')->update(['cash_handover_claimed_at' => now()]);
        abort_if($count === 0, 422, 'There’s no cash to hand over for this store.');
        self::setLink($rider, $store, ['cash_paused_at' => null]);
        if ($seller = $store->shop?->seller) {
            SellerNotify::send($seller, $rider, 'Rider says the cash is handed over', "{$rider->name} says they handed over the cash for {$count} deliver".($count === 1 ? 'y' : 'ies').'. Confirm it (Cash received) or mark it Not received in Seller Center → Local delivery.');
        }

        return $count;
    }

    /**
     * The seller didn't get the cash the rider says they handed over: the rider
     * still holds it and is paused (every store). No dispute step — the seller's
     * only cover is the rider's earnings at month end; admin can fine someone found guilty.
     */
    public static function notReceived(User $rider, Store $store, string $reason): int
    {
        $count = self::outstanding($rider, $store)->whereNotNull('cash_handover_claimed_at')->update(['cash_handover_claimed_at' => null, 'cash_disputed_at' => null, 'cash_dispute_note' => null]);
        abort_if($count === 0, 422, 'There’s no handover to answer.');
        $shop = $store->shop?->name ?? $store->name;
        self::tell($rider, 'Cash not received', "{$shop} says they didn’t get the cash you handed over: {$reason}. You still hold it and are paused until it’s handed over; if it isn’t, it comes out of your earnings.");
        self::pause($rider, $store, 'the cash you said you handed over wasn’t received');

        return $count;
    }

    public static function heldCents(User $rider, Store $store): int
    {
        return (int) self::unclaimed($rider, $store)->get()->sum(fn ($p) => SellerRiders::cashCents($p));
    }

    private static function link(User $rider, Store $store): ?object
    {
        return DB::table('rider_store')->where('user_id', $rider->id)->where('store_id', $store->id)->first();
    }

    public static function paused(User $rider, Store $store): bool
    {
        return (bool) self::link($rider, $store)?->cash_paused_at;
    }

    private static function setLink(User $rider, Store $store, array $values): void
    {
        DB::table('rider_store')->where('user_id', $rider->id)->where('store_id', $store->id)->update($values);
    }

    private static function money(Store $store, int $cents): string
    {
        return Money::format($cents, Market::currency($store->country));
    }

    /** Right after a rider delivers a cash order: tell both, and pause them if they're over the limit. */
    public static function collected(OrderPackage $package, User $rider): void
    {
        $store = SellerStores::ensure($package->shop);
        $cash = SellerRiders::cashCents($package);
        $held = self::heldCents($rider, $store);
        $shop = $package->shop->name;
        self::tell($rider, 'Cash collected', 'You collected '.self::money($store, $cash)." for {$shop} (order #{$package->order_id}). You now hold ".self::money($store, $held)." of {$shop}’s cash — hand it over at the store today.");
        if ($seller = $package->shop->seller) {
            SellerNotify::send($seller, $rider, 'Your rider collected cash', "{$rider->name} collected ".self::money($store, $cash)." on order #{$package->order_id} and now holds ".self::money($store, $held).' of your cash. Mark it received in Seller Center → Local delivery when they hand it over.');
        }
        $link = self::link($rider, $store);
        if ($held > self::limitCents($store) && ! $link?->cash_later_at) {
            self::pause($rider, $store, 'over the cash limit ('.self::money($store, self::limitCents($store)).')');
        }
    }

    /** Pause the rider for this store until the seller marks the cash received. */
    public static function pause(User $rider, Store $store, string $why): void
    {
        if (self::paused($rider, $store)) {
            return;
        }
        self::setLink($rider, $store, ['cash_paused_at' => now()]);
        $shop = $store->shop?->name ?? $store->name;
        $held = self::money($store, self::heldCents($rider, $store));
        self::tell($rider, "Paused for {$shop}", "You’re paused for {$shop}: {$why}. Visit the store and hand over the {$held} you hold — until then you can’t take deliveries for any store.");
        if ($seller = $store->shop?->seller) {
            SellerNotify::send($seller, $rider, 'Rider paused — cash to collect', "{$rider->name} is paused for your store ({$why}). Collect the {$held} they hold and mark it received to let them deliver again — or choose Later, at your own risk.");
        }
    }

    /** The seller got the cash: everything outstanding is handed over, and the rider can deliver again. */
    public static function received(User $rider, Store $store): int
    {
        $count = self::outstanding($rider, $store)->update(['cash_handed_over_at' => now()]);
        self::setLink($rider, $store, ['cash_paused_at' => null, 'cash_later_at' => null]);
        self::tell($rider, 'Cash received', ($store->shop?->name ?? 'The store').' marked your cash as received — you can take their deliveries again.');

        return $count;
    }

    /** "Later": the seller trusts the rider to keep working over the limit until the next handover (seller's own risk). */
    public static function later(User $rider, Store $store): void
    {
        self::setLink($rider, $store, ['cash_paused_at' => null, 'cash_later_at' => now()]);
        self::tell($rider, 'You can keep delivering', ($store->shop?->name ?? 'The store').' lets you keep delivering — hand over the cash you hold as soon as you can.');
    }

    /** End of the day: remind riders and sellers of cash not handed over (and admin, for the store's own riders). */
    public static function endOfDay(): int
    {
        $n = 0;
        // The store's own riders: cash on delivery they still hold — to the rider and the admins.
        foreach (User::query()->where('is_rider', true)->get() as $rider) {
            $held = $rider->codHoldingCents();
            if ($held <= 0) {
                continue;
            }
            $country = $rider->stores()->whereNull('shop_id')->value('country');
            $amount = Money::format($held, Market::currency($country));
            self::tell($rider, 'Cash to return', "You hold {$amount} of cash on delivery. Return it to the store.");
            try {
                \Illuminate\Support\Facades\Notification::send(User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice("{$rider->name} holds {$amount} in cash", "End of day: {$rider->name} still holds {$amount} of cash on delivery. Settle it under Riders."));
            } catch (\Throwable $e) {
                report($e);
            }
            $n++;
        }
        // Sellers who haven't answered a rider's "I handed over the cash".
        $claims = OrderPackage::query()->whereNotNull('cash_handover_claimed_at')->whereNull('cash_handed_over_at')->get(['rider_id', 'shop_id'])->unique(fn ($p) => $p->rider_id.'-'.$p->shop_id);
        foreach ($claims as $claim) {
            $store = Store::query()->where('shop_id', $claim->shop_id)->with('shop.seller')->first();
            $rider = User::find($claim->rider_id);
            if ($store?->shop?->seller && $rider) {
                SellerNotify::send($store->shop->seller, $rider, 'Confirm a cash handover', "{$rider->name} says they handed over cash to you. Confirm it (Cash received) or mark it Not received in Seller Center → Local delivery.");
                $n++;
            }
        }
        foreach (self::holders() as [$rider, $store, $held]) {
            $shop = $store->shop?->name ?? $store->name;
            self::tell($rider, 'Hand over your cash today', 'You still hold '.self::money($store, $held)." of {$shop}’s cash. Hand it over at the store — tomorrow you’re paused for {$shop} until you do.");
            if ($seller = $store->shop?->seller) {
                SellerNotify::send($seller, $rider, 'Cash not handed over yet', "{$rider->name} still holds ".self::money($store, $held).' of your cash from today. Collect it and mark it received.');
            }
            $n++;
        }

        return $n;
    }

    /** Next morning: riders still holding yesterday's cash are paused (unless the seller chose Later). */
    public static function morning(): int
    {
        $n = 0;
        foreach (self::holders(before: today()) as [$rider, $store, $held]) {
            // Owed more in earnings than the cash they hold: a few days' grace (the earnings cover it).
            $oldest = self::outstanding($rider, $store)->min('cash_collected_at');
            $grace = RiderLedger::balanceCents($rider) >= RiderMoney::cashHeldCents($rider) && $oldest && Carbon::parse($oldest)->gt(now()->subDays(3));
            if (! $grace && ! self::link($rider, $store)?->cash_later_at) {
                self::pause($rider, $store, 'cash from yesterday not handed over');
                $n++;
            }
        }

        return $n;
    }

    /** @return list<array{0: User, 1: Store, 2: int}> riders holding a seller's cash (collected before $before, if given) */
    private static function holders($before = null): array
    {
        $rows = OrderPackage::query()->whereNotNull('rider_id')->whereNotNull('cash_collected_at')->whereNull('cash_handed_over_at')
            ->when($before, fn ($q) => $q->where('cash_collected_at', '<', $before))
            ->get(['rider_id', 'shop_id'])->unique(fn ($p) => $p->rider_id.'-'.$p->shop_id);
        $out = [];
        foreach ($rows as $row) {
            $rider = User::find($row->rider_id);
            $store = Store::query()->where('shop_id', $row->shop_id)->with('shop.seller')->first();
            if ($rider && $store && ($held = self::heldCents($rider, $store)) > 0) {
                $out[] = [$rider, $store, $held];
            }
        }

        return $out;
    }

    private static function tell(User $rider, string $subject, string $body): void
    {
        try {
            $rider->notify(new RiderNotice($subject, $body));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
