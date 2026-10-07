<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Shop;
use App\Notifications\SellerNewOrder;

/**
 * Tells sellers about a newly confirmed order with their items: an email to
 * each shop on the order (Seller Center also alerts in-app, polling
 * GET /seller/orders/alerts). A failure never breaks the checkout.
 */
class SellerOrderAlerts
{
    public static function newOrder(Order $order): void
    {
        $order->loadMissing(['items', 'shopShipping']);
        foreach ($order->items->whereNotNull('shop_id')->groupBy('shop_id') as $shopId => $items) {
            try {
                $shop = Shop::with('seller.user')->find($shopId);
                $user = $shop?->seller?->user;
                if (! $user?->email) {
                    continue;
                }
                $shipsItself = $items->contains(fn ($i) => $i->fulfilled_by === 'seller');
                $shipBy = $order->shopShipping->firstWhere('shop_id', (int) $shopId)?->ship_by?->toFormattedDateString();
                $user->notify(new SellerNewOrder($order, $items->values(), $shipsItself, $shipBy));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
