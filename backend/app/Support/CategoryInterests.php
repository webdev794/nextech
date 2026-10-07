<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Personal "Recommended": the categories a shopper looked at most recently
 * come first. A category they bought from after their last look there (in the
 * last 30 days) drops to the back — they've just got one. Signed-in shoppers
 * are tracked on the server; guests send their recent categories from the browser.
 */
class CategoryInterests
{
    /** Categories ranked first on "Recommended". */
    public const LIMIT = 8;

    /** A recent purchase lowers its category for this long, unless viewed again since. */
    private const BOUGHT_DAYS = 30;

    public static function viewed(User $user, int $categoryId): void
    {
        $now = now();
        DB::table('category_interests')->upsert(
            [['user_id' => $user->id, 'category_id' => $categoryId, 'views' => 1, 'last_viewed_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
            ['user_id', 'category_id'],
            ['views' => DB::raw('views + 1'), 'last_viewed_at' => $now, 'updated_at' => $now],
        );
    }

    /** After an order: its categories were just bought from. */
    public static function bought(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }
        $now = now();
        $rows = $order->items()->with('product:id,category_id')->get()
            ->pluck('product.category_id')->filter()->unique()
            ->map(fn ($id) => ['user_id' => $order->user_id, 'category_id' => $id, 'views' => 0, 'last_bought_at' => $now, 'created_at' => $now, 'updated_at' => $now])
            ->values()->all();
        if ($rows) {
            DB::table('category_interests')->upsert($rows, ['user_id', 'category_id'], ['last_bought_at' => $now, 'updated_at' => $now]);
        }
    }

    /**
     * Category ids in the order to show them: this user's recent views (merged
     * with the guest list from the browser), newest first; just-bought ones last.
     *
     * @param  list<int>  $recent  category ids from the browser, most recent first
     * @return list<int>
     */
    public static function ranked(?User $user, array $recent = []): array
    {
        $rows = $user ? DB::table('category_interests')->where('user_id', $user->id)->whereNotNull('last_viewed_at')
            ->orderByDesc('last_viewed_at')->limit(30)->get() : collect();
        $justBought = $rows->filter(fn ($r) => $r->last_bought_at && $r->last_bought_at >= $r->last_viewed_at && $r->last_bought_at >= now()->subDays(self::BOUGHT_DAYS))->pluck('category_id')->all();

        $order = array_values(array_unique(array_merge(array_map('intval', $recent), $rows->pluck('category_id')->map(fn ($id) => (int) $id)->all())));
        $fresh = array_values(array_diff($order, $justBought));
        $ids = array_slice(array_merge($fresh, array_values(array_intersect($order, $justBought))), 0, self::LIMIT);

        return Category::query()->whereIn('id', $ids)->where('is_active', true)->pluck('id')
            ->sortBy(fn ($id) => array_search((int) $id, $ids, true))->values()->map(fn ($id) => (int) $id)->all();
    }
}
