<?php

namespace App\Support;

use App\Models\OrderShopShipping;
use App\Models\ProductReview;
use App\Models\RiderLeave;
use App\Models\RiderReview;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\AdminNotice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Daily performance watch for admin (last 30 days): sellers whose products get
 * poor reviews or who keep shipping late, riders with poor delivery ratings or
 * missed working days, and products rated poorly. New warnings go to the admin
 * 🔔 (until dismissed) and by email; the same warning isn't repeated within a week.
 */
final class PerformanceWatch
{
    private const MIN_REVIEWS = 5;

    /** @return list<array<string, mixed>> */
    public static function warnings(): array
    {
        return array_values((array) Setting::get('performance_warnings', []));
    }

    public static function dismiss(string $id): void
    {
        Setting::put('performance_warnings', array_values(array_filter(self::warnings(), fn ($w) => $w['id'] !== $id)));
    }

    public static function check(): int
    {
        $since = now()->subDays(30);
        $found = [];

        // Products rated poorly.
        $products = ProductReview::query()->where('created_at', '>=', $since)->selectRaw('product_id, avg(rating) as a, count(*) as c')->groupBy('product_id')->havingRaw('count(*) >= ?', [self::MIN_REVIEWS])->get();
        foreach ($products as $p) {
            if ((float) $p->a < 2.5 && ($product = \App\Models\Product::find($p->product_id))) {
                $found[] = ['kind' => 'product', 'key' => "product-{$product->id}", 'link' => ['product_id' => $product->id], 'text' => "Product “{$product->name}” is rated ★".round((float) $p->a, 1)." from {$p->c} reviews in 30 days."];
            }
        }
        // Sellers: poor reviews across their products, or late shipping.
        foreach (Shop::query()->whereNotNull('seller_id')->with('seller')->get() as $shop) {
            $r = ProductReview::query()->where('created_at', '>=', $since)->whereHas('product', fn ($q) => $q->where('shop_id', $shop->id))->selectRaw('avg(rating) as a, count(*) as c, sum(case when rating <= 2 then 1 else 0 end) as low')->first();
            $late = OrderShopShipping::query()->where('shop_id', $shop->id)->where('created_at', '>=', $since)->whereNotNull('escalated_at')->count();
            $why = [];
            if ((int) $r->c >= self::MIN_REVIEWS && (float) $r->a < 3.0) {
                $why[] = 'products rated ★'.round((float) $r->a, 1)." ({$r->c} reviews, {$r->low} with 1–2 stars)";
            }
            if ($late >= 3) {
                $why[] = "{$late} orders not shipped on time";
            }
            // Riders' private ratings of this seller (admin only).
            $fr = \App\Models\RiderFeedback::query()->where('shop_id', $shop->id)->whereNotNull('store_rating')->where('created_at', '>=', $since)->selectRaw('avg(store_rating) as a, count(*) as c')->first();
            if ((int) $fr->c >= 3 && (float) $fr->a < 3.0) {
                $why[] = 'riders rate the store ★'.round((float) $fr->a, 1)." ({$fr->c} ratings)";
            }
            if ($why) {
                $found[] = ['kind' => 'seller', 'key' => "seller-{$shop->id}", 'link' => ['seller_id' => $shop->seller_id], 'text' => "Seller {$shop->name}: ".implode('; ', $why).' in 30 days.'];
            }
        }
        // Riders: poor delivery ratings, or missed working days.
        foreach (User::query()->where('is_rider', true)->where('rider_is_active', true)->get() as $rider) {
            $r = RiderReview::query()->where('rider_id', $rider->id)->where('created_at', '>=', $since)->selectRaw('avg(rating) as a, count(*) as c')->first();
            $missed = RiderLeave::query()->where('user_id', $rider->id)->where('kind', 'absent')->whereDate('date', '>=', $since->toDateString())->count();
            $why = [];
            if ((int) $r->c >= self::MIN_REVIEWS && (float) $r->a < 3.5) {
                $why[] = 'deliveries rated ★'.round((float) $r->a, 1)." ({$r->c} ratings)";
            }
            if ($missed >= 3) {
                $why[] = "{$missed} working days missed without asking";
            }
            if ($why) {
                $found[] = ['kind' => 'rider', 'key' => "rider-{$rider->id}", 'link' => ['rider_id' => $rider->id], 'text' => "Rider {$rider->name}: ".implode('; ', $why).' in 30 days.'];
            }
        }

        // Buyers riders keep rating poorly (rude, not available, wrong address…).
        $buyers = \App\Models\RiderFeedback::query()->whereNotNull('buyer_rating')->where('rider_feedback.created_at', '>=', $since)
            ->join('orders', 'orders.id', '=', 'rider_feedback.order_id')->selectRaw('orders.user_id as u, avg(buyer_rating) as a, count(*) as c')
            ->groupBy('orders.user_id')->havingRaw('count(*) >= 3')->get();
        foreach ($buyers as $b) {
            if ((float) $b->a < 2.5 && ($buyer = User::find($b->u))) {
                $found[] = ['kind' => 'buyer', 'key' => "buyer-{$buyer->id}", 'link' => ['customer_id' => $buyer->id], 'text' => "Buyer {$buyer->name}: riders rate them ★".round((float) $b->a, 1)." ({$b->c} deliveries) in 30 days."];
            }
        }

        $list = self::warnings();
        $new = [];
        foreach ($found as $w) {
            if (! Cache::add('perf-'.$w['key'], 1, now()->addWeek())) {
                continue; // told within the last week
            }
            $item = $w + ['id' => (string) Str::uuid(), 'at' => now()->toIso8601String()];
            $list = array_values(array_filter($list, fn ($x) => $x['key'] !== $w['key']));
            $list[] = $item;
            $new[] = $item;
        }
        Setting::put('performance_warnings', $list);
        if ($new) {
            try {
                Notification::send(User::where('is_admin', true)->get(), new AdminNotice('Performance warnings', implode("\n", array_map(fn ($w) => '• '.$w['text'], $new))));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return count($new);
    }
}
