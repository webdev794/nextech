<?php

namespace App\Support;

use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminSellerCashKept;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\SellerLedgerEntry;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The seller's step-by-step updates on orders they ship themselves, which
 * buyers and admin follow on the order: packed, picked up by the courier,
 * in transit, out for delivery, delivered — and, for cash on delivery, cash
 * collected. Live courier tracking (LiveTracking) records the same steps
 * automatically. Sellers are reminded when an order goes quiet.
 */
class SellerProgress
{
    /** Package steps after pickup, in order. */
    public const STEPS = ['shipped', 'in_transit', 'out_for_delivery', 'delivered'];

    /** Still on its way (can move on, tracking can be corrected). */
    public const MOVING = ['shipped', 'in_transit', 'out_for_delivery'];

    /** No update for this long on a moving package → remind the seller. */
    public const QUIET_HOURS = 48;

    public const LABELS = [
        'packed' => 'Packed', 'shipped' => 'Picked up by courier', 'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered', 'returned' => 'Returned', 'lost' => 'Lost',
    ];

    /**
     * Why cash on delivery can't be used for these cart lines, or null when it
     * can. Seller-shipped items qualify when the whole cart comes from one
     * seller who accepts it and ships within the buyer's country (their
     * courier collects the cash in one go).
     *
     * @param  iterable<\App\Models\Product>  $products
     */
    public static function codBlockedReason(iterable $products, string $market): ?string
    {
        $products = collect($products)->filter();
        if ($products->contains(fn ($p) => $p->isDigital())) {
            return 'Digital downloads are paid online — choose card.';
        }
        $shops =$products->map(fn ($p) => $p->shop_id && $p->shop?->shipsItself() ? $p->shop : null);
        if ($shops->filter()->isEmpty()) {
            return null; // nothing shipped by sellers: NexTech's own cash on delivery
        }
        if ($shops->contains(null) || $shops->filter()->pluck('id')->unique()->count() > 1) {
            return 'Cash on delivery works for seller-shipped items when everything in your cart comes from one seller — pay by card, or check out separately.';
        }
        $shop = $shops->first();
        if (! $shop->accepts_cod || SellerCod::sellerReason($shop) !== null) {
            return "{$shop->name} doesn't accept cash on delivery — pay by card instead.";
        }
        if ($shop->market !== strtoupper($market)) {
            return 'Cash on delivery isn’t available for items shipped from another country — pay by card instead.';
        }

        return null;
    }

    /** One history entry for a package's step. */
    public static function entry(string $status, string $by, ?string $note = null): array
    {
        return array_filter(['status' => $status, 'at' => now()->toIso8601String(), 'by' => $by, 'note' => $note]);
    }

    /**
     * Move a package to a step (only forwards; delivered is final), stamping
     * it in its history. $cash confirms cash on delivery was collected.
     */
    public static function advance(OrderPackage $package, string $status, string $by, bool $cash = false): OrderPackage
    {
        $from = array_search($package->status, self::STEPS, true);
        $to = array_search($status, self::STEPS, true);
        abort_if($to === false, 422, 'Unknown step.');
        abort_if($from === false, 422, 'This package is '.(self::LABELS[$package->status] ?? $package->status).' — it can\'t be updated.');
        abort_if($to < $from || ($to === $from && ! ($status === 'delivered' && $cash && ! $package->cash_collected_at)), 422, 'That step is already done.');
        $cod = $package->order?->payment_method === 'cod';
        abort_if($cod && $status === 'delivered' && ! $cash && $by === 'seller', 422, 'Cash on delivery: confirm the cash was collected when marking it delivered.');

        DB::transaction(function () use ($package, $status, $by, $cash, $cod) {
            $history = (array) $package->status_history;
            if ($package->status !== $status) {
                $history[] = self::entry($status, $by);
            }
            if ($cod && $cash && ! $package->cash_collected_at) {
                $history[] = self::entry('cash_collected', $by);
            }
            $package->forceFill([
                'status' => $status,
                'status_history' => $history,
                'progress_updated_at' => now(),
                'delivered_at' => $status === 'delivered' ? ($package->delivered_at ?? now()) : $package->delivered_at,
                'cash_collected_at' => $cod && $cash ? ($package->cash_collected_at ?? now()) : $package->cash_collected_at,
            ])->save();
            SellerFulfillment::sync($package->order);
        });
        self::settleCod($package->order);

        return $package->fresh();
    }

    /** The seller packed their part of an order (before handing it to the courier). */
    public static function markPacked(Order $order, Shop $shop): void
    {
        $row = $order->shopShipping()->where('shop_id', $shop->id)->first();
        abort_unless($row, 404, 'Nothing on this order ships from your shop.');
        abort_if($row->packed_at !== null, 422, 'Already marked packed.');
        $row->update(['packed_at' => now()]);
    }

    /**
     * Cash on delivery on a seller-shipped order: once every package is
     * delivered and the seller has confirmed the cash, the order is paid. The
     * seller is credited as usual and then charged the cash they're holding,
     * so NexTech's commission and fees come out of their next payouts.
     */
    public static function settleCod(Order $order): void
    {
        $order->refresh();
        if ($order->payment_method !== 'cod' || $order->payment_status === 'paid' || $order->delivery_method !== 'seller') {
            return;
        }
        $packages = $order->packages()->get();
        if ($packages->isEmpty() || $packages->contains(fn ($p) => $p->status !== 'delivered' || ! $p->cash_collected_at)) {
            return;
        }

        $keptBy = [];
        DB::transaction(function () use ($order, &$keptBy) {
            $order->forceFill(['payment_status' => 'paid'])->save();
            SellerLedger::creditForOrder($order);
            foreach ($order->items()->whereNotNull('shop_id')->pluck('shop_id')->unique() as $shopId) {
                if (! SellerLedgerEntry::where('order_id', $order->id)->where('shop_id', $shopId)->where('type', 'cod_cash_held')->exists()) {
                    SellerLedgerEntry::create([
                        'shop_id' => $shopId, 'order_id' => $order->id, 'type' => 'cod_cash_held',
                        'amount_cents' => -(int) $order->total_cents,
                        'note' => 'Cash on delivery collected by you — '.\App\Support\Branding::name().'\'s commission and fees come out of your balance (your next orders\' earnings)',
                    ]);
                    $keptBy[] = $shopId;
                }
            }
        });
        // Tell the admins a seller kept the cash (and what they now owe).
        foreach (Shop::whereIn('id', $keptBy)->get() as $shop) {
            try {
                Notification::send(User::where('is_admin', true)->get(), new AdminSellerCashKept($order, $shop));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $order->refresh()->sendDeliveredReceiptIfReady();
    }

    /**
     * What a shop should update now: orders past their ship-by date that
     * aren't packed or shipped, packages with no update for QUIET_HOURS, and
     * delivered cash-on-delivery packages whose cash isn't confirmed.
     *
     * @return Collection<int, array{order_id: int, reason: string}>
     */
    public static function needsUpdate(Shop $shop): Collection
    {
        $quiet = now()->subHours(self::QUIET_HOURS);
        $out = collect();

        $late = Order::query()
            ->whereNotIn('status', ['pending_payment', 'cancelled', 'completed'])
            ->whereHas('shopShipping', fn ($q) => $q->where('shop_id', $shop->id)->whereDate('ship_by', '<=', today()))
            ->whereDoesntHave('packages', fn ($q) => $q->where('shop_id', $shop->id))
            ->with(['shopShipping' => fn ($q) => $q->where('shop_id', $shop->id)])
            ->get(['id']);
        foreach ($late as $o) {
            $packed = $o->shopShipping->first()?->packed_at;
            $out->push(['order_id' => $o->id, 'reason' => $packed ? 'Packed — hand it to the courier and add the tracking number' : 'Due to ship — pack it and hand it to the courier']);
        }

        OrderPackage::where('shop_id', $shop->id)->whereIn('status', self::MOVING)
            ->where(fn ($q) => $q->where('progress_updated_at', '<', $quiet)->orWhere(fn ($w) => $w->whereNull('progress_updated_at')->where('shipped_at', '<', $quiet)))
            ->whereNull('tracking_ref') // live courier tracking updates these itself
            ->get(['id', 'order_id', 'status'])
            ->each(fn ($p) => $out->push(['order_id' => $p->order_id, 'reason' => 'No update for 2 days — still '.strtolower(self::LABELS[$p->status]).'?']));

        OrderPackage::where('shop_id', $shop->id)->where('status', 'delivered')->whereNull('cash_collected_at')
            ->whereHas('order', fn ($q) => $q->where('payment_method', 'cod')->where('payment_status', '!=', 'paid'))
            ->get(['id', 'order_id'])
            ->each(fn ($p) => $out->push(['order_id' => $p->order_id, 'reason' => 'Delivered — confirm the cash on delivery was collected']));

        return $out->unique('order_id')->values();
    }
}
