<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only, seller-scoped order activity: active/completed/cancelled counts
 * and a recent-orders list, showing only this shop's own line items within
 * each order — never the full order total, other shops' items, or the
 * customer's identity. `/admin` remains the only place with full order and
 * customer access.
 */
class SellerOrderController extends Controller
{
    /** Same "active" bucket AdminController::metrics() already uses. */
    private const ACTIVE_STATUSES = ['confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery'];

    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $base = Order::whereHas('items', fn ($q) => $q->where('shop_id', $shop->id));

        $summary = [
            'active' => (clone $base)->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $orders = (clone $base)
            ->with(['items' => fn ($q) => $q->where('shop_id', $shop->id)->select('id', 'order_id', 'product_name', 'quantity', 'line_total_cents')])
            ->latest()
            ->limit(20)
            ->get(['id', 'status', 'created_at']);

        return response()->json(['data' => ['summary' => $summary, 'orders' => $orders]]);
    }

    /**
     * The shop's own performance chart: per day/week/month, orders containing
     * its items split into completed / cancelled, and what the seller earned
     * (their ledger credits minus refunds). Gross sale prices are deliberately
     * left out, so the platform's commission isn't laid side by side with the
     * payout. Plus window totals and top products by units sold. Grouped in
     * PHP, like AdminController::ordersTimeseries().
     */
    public function stats(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'bucket' => ['sometimes', 'in:day,week,month'],
            'tz' => ['sometimes', 'nullable', 'string'],
        ]);

        $tz = 'UTC';
        if (! empty($validated['tz'])) {
            try {
                new \DateTimeZone($validated['tz']);
                $tz = $validated['tz'];
            } catch (\Throwable) {
                // keep UTC
            }
        }

        $bucket = $validated['bucket'] ?? 'day';
        $to = now($tz)->endOfDay();
        $from = match ($bucket) {
            'day' => $to->copy()->subDays(13)->startOfDay(),
            'week' => $to->copy()->subWeeks(11)->startOfWeek(Carbon::MONDAY),
            'month' => $to->copy()->subMonths(11)->startOfMonth(),
        };
        $keyFor = fn (Carbon $d): string => match ($bucket) {
            'day' => $d->format('Y-m-d'),
            'week' => $d->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'month' => $d->format('Y-m-01'),
        };
        $range = [$from->copy()->utc(), $to->copy()->utc()];
        $empty = ['orders' => 0, 'completed' => 0, 'cancelled' => 0, 'earnings_cents' => 0];
        $totals = ['orders' => 0, 'completed' => 0, 'cancelled' => 0, 'active' => 0, 'earnings_cents' => 0, 'units' => 0];

        $orders = Order::query()
            ->whereBetween('created_at', $range)
            ->whereHas('items', fn ($q) => $q->where('shop_id', $shop->id))
            ->with(['items' => fn ($q) => $q->where('shop_id', $shop->id)->select('id', 'order_id', 'product_name', 'quantity')])
            ->get(['id', 'status', 'created_at']);

        $agg = [];
        $top = [];
        foreach ($orders as $order) {
            $key = $keyFor($order->created_at->copy()->setTimezone($tz));
            $agg[$key] ??= $empty;
            $agg[$key]['orders']++;
            $totals['orders']++;

            if ($order->status === 'cancelled') {
                $agg[$key]['cancelled']++;
                $totals['cancelled']++;

                continue;
            }
            if ($order->status === 'completed') {
                $agg[$key]['completed']++;
                $totals['completed']++;
            } else {
                $totals['active']++;
            }

            foreach ($order->items as $item) {
                $top[$item->product_name] ??= ['label' => $item->product_name, 'quantity' => 0];
                $top[$item->product_name]['quantity'] += (int) $item->quantity;
                $totals['units'] += (int) $item->quantity;
            }
        }

        $ledger = SellerLedgerEntry::query()
            ->where('shop_id', $shop->id)
            ->whereIn('type', ['order_credit', 'refund_debit'])
            ->whereBetween('created_at', $range)
            ->get(['created_at', 'amount_cents']);
        foreach ($ledger as $entry) {
            $key = $keyFor($entry->created_at->copy()->setTimezone($tz));
            $agg[$key] ??= $empty;
            $agg[$key]['earnings_cents'] += (int) $entry->amount_cents;
            $totals['earnings_cents'] += (int) $entry->amount_cents;
        }

        $series = [];
        for ($cursor = $from->copy(); $cursor <= $to; match ($bucket) {
            'day' => $cursor->addDay(),
            'week' => $cursor->addWeek(),
            'month' => $cursor->addMonth(),
        }) {
            $key = $keyFor($cursor);
            $series[] = ['label' => $bucket === 'month' ? $cursor->format('M Y') : $cursor->format('M j')] + ($agg[$key] ?? $empty);
        }

        usort($top, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        return response()->json(['data' => [
            'series' => $series,
            'totals' => $totals,
            'top_products' => array_slice($top, 0, 5),
        ]]);
    }

    /**
     * The caller's own shop — only if their seller application is approved.
     * Mirrors SellerProductController::shop() exactly.
     */
    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');

        $shop = $seller->shop;
        abort_unless($shop, 404, 'No shop found for this seller.');

        return $shop;
    }
}
