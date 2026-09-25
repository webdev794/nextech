<?php

namespace App\Support;

use App\Models\LabelRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPackage;
use App\Models\SellerLedgerEntry;
use App\Models\Setting;
use App\Models\Shop;
use App\Notifications\OrderShipped;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seller-shipped order lines: what's left to ship, and keeping the order in
 * step as packages ship and get delivered. An order whose every line ships
 * from sellers (delivery_method "seller") never goes through NexTech's
 * packing/rider flow — it goes out for delivery once everything has shipped
 * and completes once every package is delivered. Mixed orders keep NexTech's
 * flow for NexTech's part; the seller packages are tracked alongside.
 */
class SellerFulfillment
{
    /** Units of this line not yet in a package. */
    public static function remainingQuantity(OrderItem $item): int
    {
        $shipped = (int) $item->packageItems()->sum('quantity');

        return max(0, (int) $item->quantity - $shipped);
    }

    /**
     * How NexTech labels are issued: 'auto' = bought instantly through the
     * connected courier API; 'manual' = the seller requests one and an admin
     * uploads the label file (tracking + postage) for the seller to download.
     */
    public static function labelMode(): string
    {
        return Setting::get('nextech_label_mode', 'manual') === 'auto' ? 'auto' : 'manual';
    }

    /** Units of this line held by a label request: waiting for the label, or labelled but not shipped yet. */
    public static function requestedQuantity(OrderItem $item): int
    {
        return (int) LabelRequest::where('order_id', $item->order_id)
            ->where(fn ($q) => $q->where('status', 'requested')->orWhere(fn ($q) => $q->where('status', 'ready')->whereNull('order_package_id')))
            ->get()
            ->sum(fn (LabelRequest $r) => collect($r->items)->where('order_item_id', $item->id)->sum('quantity'));
    }

    /**
     * Ship some of a shop's lines as one package (seller's own tracking, an
     * auto-bought label, or an admin-uploaded label).
     *
     * @param  list<array{order_item_id: int, quantity: int}>  $items
     * @param  array<string, mixed>  $attributes
     */
    public static function createPackage(Order $order, Shop $shop, array $items, ?int $addressId, array $attributes, ?LabelRequest $fromRequest = null): OrderPackage
    {
        abort_if(in_array($order->status, ['pending_payment', 'cancelled'], true), 422, 'This order can\'t be shipped.');

        return DB::transaction(function () use ($order, $shop, $items, $addressId, $attributes, $fromRequest) {
            Order::whereKey($order->id)->lockForUpdate()->first();
            $lines = self::shopLines($order, $shop)->keyBy('id');
            abort_if($lines->isEmpty(), 404, 'Nothing on this order ships from that shop.');

            foreach ($items as $row) {
                $line = $lines->get($row['order_item_id']);
                abort_unless($line, 422, 'That item isn\'t one of the shop\'s on this order.');
                // Units held by other open label requests can't go in this package.
                $free = self::remainingQuantity($line) - self::requestedQuantity($line)
                    + ($fromRequest ? (int) collect($fromRequest->items)->where('order_item_id', $line->id)->sum('quantity') : 0);
                abort_if($row['quantity'] > $free, 422, "Only {$free} × {$line->product_name} left to ship.");
            }

            $package = OrderPackage::create($attributes + [
                'order_id' => $order->id,
                'shop_id' => $shop->id,
                'ship_from_address_id' => $addressId,
                'status' => 'shipped',
                'shipped_at' => now(),
            ]);
            foreach ($items as $row) {
                $package->items()->create(['order_item_id' => $row['order_item_id'], 'quantity' => $row['quantity']]);
            }
            self::sync($order);

            // Tell the customer, with carrier + tracking link (after commit).
            DB::afterCommit(fn () => $order->fresh()?->emailCustomer(new OrderShipped($order->fresh(), $package->fresh())));

            return $package->load('items');
        });
    }

    /** @return Collection<int, OrderItem> the shop's seller-shipped lines on an order */
    public static function shopLines(Order $order, Shop $shop): Collection
    {
        return $order->items()->where('shop_id', $shop->id)->where('fulfilled_by', 'seller')->get();
    }

    /** Has this shop shipped all of its lines, and have all its packages been delivered? */
    public static function shopDeliveredAt(Order $order, int $shopId): ?\Illuminate\Support\Carbon
    {
        $lines = $order->items->where('shop_id', $shopId)->where('fulfilled_by', 'seller');
        if ($lines->isEmpty()) {
            return null;
        }
        foreach ($lines as $line) {
            if (self::remainingQuantity($line) > 0) {
                return null;
            }
        }
        $packages = OrderPackage::where('order_id', $order->id)->where('shop_id', $shopId)->get();
        if ($packages->isEmpty() || $packages->contains(fn ($p) => $p->status !== 'delivered')) {
            return null;
        }

        return $packages->max('delivered_at');
    }

    /** After packages change: move a seller-only order along (out for delivery -> completed). */
    public static function sync(Order $order): void
    {
        $order->refresh();
        if (! $order->isSellerShippedOnly() || in_array($order->status, ['cancelled', 'completed', 'pending_payment'], true)) {
            return;
        }

        $lines = $order->items()->where('fulfilled_by', 'seller')->get();
        $allShipped = $lines->every(fn ($line) => self::remainingQuantity($line) === 0);
        $packages = $order->packages()->get();
        $allDelivered = $allShipped && $packages->isNotEmpty() && $packages->every(fn ($p) => $p->status === 'delivered');

        if ($allDelivered) {
            $order->forceFill([
                'status' => 'completed',
                'delivered_at' => $packages->max('delivered_at') ?? now(),
                'delivery_verified' => false,
                'delivery_note' => 'Delivered by the seller\'s courier.',
            ])->save();
            $order->refresh()->sendDeliveredReceiptIfReady();
        } elseif ($allShipped && $order->status !== 'out_for_delivery') {
            $order->forceFill(['status' => 'out_for_delivery'])->save();
        }
    }

    /** Postage for a NexTech-bought label, taken from the seller's earnings on that order. */
    public static function chargeLabel(OrderPackage $package): void
    {
        if ($package->label_cost_cents <= 0) {
            return;
        }

        SellerLedgerEntry::create([
            'shop_id' => $package->shop_id,
            'order_id' => $package->order_id,
            'type' => 'shipping_label',
            'amount_cents' => -$package->label_cost_cents,
            'note' => "{$package->carrier} label {$package->tracking_number}",
        ]);
    }
}
