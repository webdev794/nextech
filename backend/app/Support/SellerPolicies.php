<?php

namespace App\Support;

use App\Models\Page;
use App\Models\PolicyAcceptance;
use App\Models\Seller;

/**
 * Seller policies admin marks as needing acceptance — to sell at all
 * ('selling') or to sell abroad ('international'). The seller reads the page
 * to the end, ticks accept and signs with their name; the date, IP and the
 * exact text version are kept. Editing a policy's text asks every seller to
 * accept it again. The house shop needn't.
 */
class SellerPolicies
{
    public const FOR = ['selling', 'international'];

    /** A short fingerprint of what the seller is accepting: the title and text. */
    public static function version(Page $page): string
    {
        return sha1($page->title."\n".$page->content."\n".json_encode($page->sections));
    }

    /**
     * Each policy needing acceptance with this seller's acceptance of its current text.
     *
     * @return list<array{slug: string, title: string, for: string, accepted: ?array, outdated: bool}>
     */
    public static function status(Seller $seller): array
    {
        $latest = PolicyAcceptance::where('seller_id', $seller->id)->orderByDesc('accepted_at')->get()->groupBy('page_id');

        return Page::query()->published()->whereIn('acceptance_for', self::FOR)->ordered()->get()
            ->filter(fn (Page $p) => in_array('seller_footer', (array) $p->menu_placements, true))
            ->map(function (Page $p) use ($latest) {
                $last = $latest->get($p->id)?->first();
                $current = $last && $last->page_version === self::version($p);

                return [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'for' => $p->acceptance_for,
                    'accepted' => $current ? ['signed_name' => $last->signed_name, 'accepted_at' => $last->accepted_at] : null,
                    // Accepted before, but the policy has changed since.
                    'outdated' => $last !== null && ! $current,
                ];
            })->values()->all();
    }

    /** Policies this seller still has to accept for $for ('selling' or 'international'). */
    public static function pending(Seller $seller, string $for = 'selling'): array
    {
        if ($seller->shop?->is_house) {
            return [];
        }

        return array_values(array_filter(self::status($seller), fn ($row) => $row['accepted'] === null && $row['for'] === $for));
    }

    /** Stops the action until the seller has signed what it needs. */
    public static function assertAccepted(Seller $seller, string $for = 'selling'): void
    {
        $missing = self::pending($seller, $for);
        if ($missing !== []) {
            // The Seller Center opens these one by one, then retries the action.
            abort(response()->json([
                'message' => 'First read and accept: '.implode(', ', array_column($missing, 'title')).'.',
                'policies_required' => array_map(fn ($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'for' => $p['for'], 'outdated' => $p['outdated']], $missing),
            ], 422));
        }
    }
}
