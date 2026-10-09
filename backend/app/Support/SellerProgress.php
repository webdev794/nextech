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

    /** No update for this long on a courier package (no live tracking) → remind the seller. */
    public const QUIET_HOURS = 48;

    /**
     * Admin's rules for chasing sellers (Settings → Shipping): remind when an
     * order isn't packed after pack_hours, repeat every repeat_hours while it's
     * still waiting, and alert admin escalate_hours after a missed ship-by date.
     *
     * @return array{pack_hours: int, repeat_hours: int, escalate_hours: int}
     */
    public static function rules(): array
    {
        $r = (array) \App\Models\Setting::get('seller_update_rules', []);

        return [
            'pack_hours' => max(1, (int) ($r['pack_hours'] ?? 12)),
            'repeat_hours' => max(1, (int) ($r['repeat_hours'] ?? 12)),
            'escalate_hours' => max(1, (int) ($r['escalate_hours'] ?? 24)),
        ];
    }

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
    public static function codBlockedReason(iterable $products, string $market, ?int $totalCents = null, ?bool $local = null): ?string
    {
        $products = collect($products)->filter();
        // Large orders aren't offered cash on delivery: no more than a rider may carry (admin / seller limit).
        $sellerShop = $products->first(fn ($p) => $p->shop_id && $p->shop?->shipsItself())?->shop;
        if ($totalCents !== null && $totalCents > ($max = SellerRiderCash::codMaxCents($market, $sellerShop))) {
            return 'Cash on delivery is available on orders up to '.Money::format($max, Market::currency($market)).' — pay by card instead.';
        }
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
        // Admin allows sellers' cash on delivery only on local deliveries ($local null = not known yet; checked again once it is).
        if (SellerCod::mode() === 'local' && $local === false) {
            return "{$shop->name} takes cash on delivery only for buyers near their shop (local delivery) — pay by card instead.";
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
        $rules = self::rules();
        $out = collect();

        // New orders not packed yet after pack_hours (before their ship-by date).
        Order::query()
            ->whereNotIn('status', ['pending_payment', 'cancelled', 'completed'])
            ->where('created_at', '<=', now()->subHours($rules['pack_hours']))
            ->whereHas('shopShipping', fn ($q) => $q->where('shop_id', $shop->id)->whereNull('packed_at')->whereDate('ship_by', '>', today()))
            ->whereDoesntHave('packages', fn ($q) => $q->where('shop_id', $shop->id))
            ->get(['id'])
            ->each(fn ($o) => $out->push(['order_id' => $o->id, 'reason' => 'Not packed yet — pack it and mark it Packed']));

        $late = Order::query()
            ->whereNotIn('status', ['pending_payment', 'cancelled', 'completed'])
            ->whereHas('shopShipping', fn ($q) => $q->where('shop_id', $shop->id)->whereDate('ship_by', '<=', today()))
            ->whereDoesntHave('packages', fn ($q) => $q->where('shop_id', $shop->id))
            ->with(['shopShipping' => fn ($q) => $q->where('shop_id', $shop->id)])
            ->get(['id']);
        foreach ($late as $o) {
            $ship = $o->shopShipping->first();
            $packed = $ship?->packed_at;
            $out->push(['order_id' => $o->id, 'reason' => $ship?->method === 'local'
                ? 'Due today — send it out with your delivery person (Out for delivery)'
                : ($packed ? 'Packed — hand it to the courier and add the tracking number' : 'Due to ship — pack it and hand it to the courier')]);
        }

        // Own delivery: out for delivery and no update after repeat_hours.
        OrderPackage::where('shop_id', $shop->id)->where('label_source', 'local')->where('status', 'out_for_delivery')
            ->where('progress_updated_at', '<', now()->subHours($rules['repeat_hours']))
            ->get(['id', 'order_id'])
            ->each(fn ($p) => $out->push(['order_id' => $p->order_id, 'reason' => 'Out for delivery for a while — delivered? Enter the buyer\'s delivery code']));

        OrderPackage::where('shop_id', $shop->id)->whereIn('status', self::MOVING)->where('label_source', '!=', 'local')
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

    /**
     * Hourly: remind each seller about orders waiting on them (email), at most
     * once every repeat_hours per order, and alert admin once about orders
     * still not shipped escalate_hours after their ship-by date.
     *
     * @return array{reminded: int, escalated: int}
     */
    /**
     * Arrival deadlines (the "arrives by" date the buyer was shown): a seller's part due
     * today or tomorrow and not delivered → the seller is reminded (once a day); past the
     * date and still not delivered → admin is told once.
     */
    public static function arrivalDeadlines(): int
    {
        $n = 0;
        $late = [];
        $rows = \App\Models\OrderShopShipping::query()->whereNotNull('deliver_by')->whereDate('deliver_by', '<=', now()->addDay()->toDateString())
            ->whereDate('deliver_by', '>=', now()->subDays(14)->toDateString())
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'completed']))->with('shop.seller')->get();
        foreach ($rows as $row) {
            $packages = OrderPackage::where('order_id', $row->order_id)->where('shop_id', $row->shop_id)->get();
            if ($packages->isNotEmpty() && $packages->every(fn ($p) => in_array($p->status, ['delivered', 'returned', 'lost'], true))) {
                continue; // delivered
            }
            $by = $row->deliver_by->toDateString();
            if ($by < now()->toDateString()) {
                if (\Illuminate\Support\Facades\Cache::add("arrival-late-{$row->id}", 1, now()->addDays(60))) {
                    $late[] = "#{$row->order_id} ({$row->shop?->name}, due {$by})";
                }

                continue;
            }
            if ($row->shop?->seller && \Illuminate\Support\Facades\Cache::add("arrival-soon-{$row->id}-".now()->toDateString(), 1, now()->addDay())) {
                SellerNotify::send($row->shop->seller, $row->shop->seller->user, 'Order due to arrive '.($by === now()->toDateString() ? 'today' : 'tomorrow'),
                    "Order #{$row->order_id} should reach the buyer by ".$row->deliver_by->format('j M').' and isn’t delivered yet. '.($row->method === 'local' ? 'Send it out (you or a rider) today.' : 'Ship it now, or update the tracking.'));
                $n++;
            }
        }
        if ($late) {
            try {
                Notification::send(User::where('is_admin', true)->get(), new \App\Notifications\AdminNotice('Seller orders past their arrival date', 'Not delivered by the date the buyer was given: '.implode('; ', $late).'.'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $n + count($late);
    }

    public static function chase(): array
    {
        $rules = self::rules();
        $reminded = 0;
        $overdue = [];

        Shop::query()->where('is_active', true)->whereIn('fulfillment_mode', ['self', 'label'])->with('seller.user')
            ->each(function (Shop $shop) use ($rules, &$reminded, &$overdue) {
                $due = self::needsUpdate($shop);
                if ($due->isEmpty()) {
                    return;
                }
                $rows = \App\Models\OrderShopShipping::where('shop_id', $shop->id)->whereIn('order_id', $due->pluck('order_id'))->get()->keyBy('order_id');
                $remind = $due->filter(function ($d) use ($rows, $rules) {
                    $row = $rows->get($d['order_id']);

                    return $row && (! $row->reminded_at || $row->reminded_at->lte(now()->subHours($rules['repeat_hours'])));
                })->values();

                $user = $shop->seller?->user;
                if ($remind->isNotEmpty() && $user?->email) {
                    try {
                        $user->notify(new \App\Notifications\SellerUpdateReminder($remind));
                        foreach ($remind as $d) {
                            $row = $rows->get($d['order_id']);
                            $row->forceFill(['reminded_at' => now(), 'reminder_count' => min(255, $row->reminder_count + 1)])->save();
                        }
                        $reminded++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                // Not shipped escalate_hours after the ship-by date: tell admin once.
                foreach ($rows as $row) {
                    if (! $row->escalated_at && $row->ship_by && $row->ship_by->copy()->endOfDay()->addHours($rules['escalate_hours'])->isPast()
                        && ! OrderPackage::where('order_id', $row->order_id)->where('shop_id', $shop->id)->exists()) {
                        $row->forceFill(['escalated_at' => now()])->save();
                        $overdue[] = ['order_id' => $row->order_id, 'shop' => $shop->name, 'ship_by' => $row->ship_by->toDateString(), 'reminders' => $row->reminder_count];
                    }
                }
            });

        self::arrivalDeadlines();

        if ($overdue) {
            try {
                Notification::send(User::where('is_admin', true)->get(), new \App\Notifications\AdminSellerOverdue($overdue));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['reminded' => $reminded, 'escalated' => count($overdue)];
    }
}
