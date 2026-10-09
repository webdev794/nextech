<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Bonus suggestions: on the 25th of each month (so bonuses can be paid by the
 * month's end), riders whose month so far is excellent — many deliveries, 5-star ratings, and earnings above a normal
 * month's pay — are suggested to admin (🔔 + email) to reward with a bonus
 * (Secure access → Payouts → Adjust a balance). Their seller is told too.
 */
final class RiderBonus
{
    /** Per country: a normal month's pay, the fewest deliveries and the lowest rating that count. */
    private const DEFAULTS = [
        'US' => ['benchmark_cents' => 200000, 'min_deliveries' => 100, 'min_rating' => 4.8],
        'IN' => ['benchmark_cents' => 2000000, 'min_deliveries' => 100, 'min_rating' => 4.8],
    ];

    public static function rules(string $market): array
    {
        $saved = (array) (Setting::get('rider_bonus', [])[$market] ?? []);

        return $saved + (self::DEFAULTS[$market] ?? self::DEFAULTS['US']);
    }

    /** @return list<array<string, mixed>> */
    public static function suggestions(): array
    {
        return array_values((array) Setting::get('rider_bonus_suggestions', []));
    }

    public static function dismiss(string $id): void
    {
        Setting::put('rider_bonus_suggestions', array_values(array_filter(self::suggestions(), fn ($s) => $s['id'] !== $id)));
    }

    /** Check a month (default: this month so far — run on the 25th) and suggest bonuses. */
    public static function suggest(?Carbon $month = null): int
    {
        $month ??= now()->startOfMonth();
        $label = $month->format('M Y');
        $list = self::suggestions();
        $new = [];
        foreach (Market::codes() as $market) {
            $r = self::rules($market);
            foreach (RiderMoney::table($month, null, $market) as $row) {
                $rider = User::find($row['rider_id']);
                $reviews = $rider?->riderReviews()->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
                $rating = $reviews ? round((float) $reviews->avg('rating'), 2) : 0.0;
                if (! $rider || $row['deliveries'] < $r['min_deliveries'] || $rating < $r['min_rating'] || $row['earned_cents'] <= $r['benchmark_cents']) {
                    continue;
                }
                if (collect($list)->contains(fn ($s) => $s['rider_id'] === $rider->id && $s['month'] === $label)) {
                    continue;
                }
                $item = ['id' => (string) Str::uuid(), 'rider_id' => $rider->id, 'name' => $rider->name, 'market' => $market, 'month' => $label,
                    'deliveries' => $row['deliveries'], 'rating' => $rating, 'earned_cents' => $row['earned_cents'], 'currency' => Market::currency($market), 'at' => now()->toIso8601String()];
                $list[] = $item;
                $new[] = $item;
                // Their seller hears it too (the bonus itself is added by the store).
                foreach ($rider->stores()->whereNotNull('shop_id')->with('shop.seller')->get() as $store) {
                    if ($store->shop?->seller) {
                        SellerNotify::send($store->shop->seller, $store->shop->seller->user, 'Great rider last month', "{$rider->name} made {$row['deliveries']} deliveries in {$label} with a ★{$rating} rating. ".Branding::name().' may add a bonus.');
                    }
                }
            }
        }
        Setting::put('rider_bonus_suggestions', $list);
        if ($new !== []) {
            try {
                Notification::send(User::where('is_admin', true)->get(), new AdminNotice('Bonus suggestions for riders',
                    "Riders who did excellent work in {$label}: ".collect($new)->map(fn ($s) => "{$s['name']} ({$s['deliveries']} deliveries, ★{$s['rating']}, earned ".Money::format($s['earned_cents'], $s['currency']).')')->join('; ').'. Add a bonus in Secure access → Payouts → Adjust a balance.'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return count($new);
    }
}
