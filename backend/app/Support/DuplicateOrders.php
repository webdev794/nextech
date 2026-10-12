<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Shop;
use App\Models\SupportThread;
use App\Models\User;

/**
 * A buyer places the same order again (same items, within a day): an automatic
 * message from the seller's side (or support, for the store's own items) asks
 * whether it was a mistake. The buyer's reply reaches the seller (customer chats).
 */
final class DuplicateOrders
{
    public static function check(Order $order): void
    {
        $order->loadMissing('items');
        $groups = $order->items->where('fulfilled_by', '!=', 'digital')->groupBy(fn ($i) => (int) $i->shop_id);
        foreach ($groups as $shopId => $items) {
            $sig = self::signature($items);
            $earlier = Order::query()->where('user_id', $order->user_id)->whereKeyNot($order->id)
                ->whereNotIn('status', ['cancelled'])->where('created_at', '>=', now()->subDay())
                ->with('items')->latest('id')->get()
                ->first(fn ($o) => self::signature($o->items->where('fulfilled_by', '!=', 'digital')->where('shop_id', $shopId ?: null)) === $sig);
            if (! $earlier) {
                continue;
            }
            $shop = $shopId ? Shop::with('seller.user')->find($shopId) : null;
            $thread = SupportThread::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'issue_type' => 'other', 'status' => 'open',
                'seller_shop_id' => $shop?->id, 'seller_joined_at' => $shop ? now() : null]);
            $text = "Hi! You placed the same order twice — #{$earlier->id} and #{$order->id}. Was the second one by mistake, or would you like both? Just reply here and we’ll sort it out.";
            $shop?->seller?->user
                ? $thread->post($shop->seller->user, $text, isStaff: true, fromSeller: true)
                : $thread->post(null, $text, system: true);
        }
    }

    /** The items of an order as "product:variant:qty" — the same set means the same order. */
    private static function signature($items): string
    {
        return $items->map(fn ($i) => $i->product_id.':'.($i->variant_label ?? '').':'.$i->quantity)->sort()->implode('|');
    }

    /** A buyer answered in a chat linked to a seller: the seller hears it (email). */
    public static function tellSeller(SupportThread $thread, User $buyer): void
    {
        $seller = $thread->seller_shop_id ? Shop::with('seller.user')->find($thread->seller_shop_id)?->seller?->user : null;
        if ($seller) {
            try {
                $seller->notify(new \App\Notifications\SellerNotice('A buyer replied', 'A buyer replied in your customer chat'.($thread->order_id ? " about order #{$thread->order_id}" : '').'. Open Seller Center → Messages to continue.', true));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
