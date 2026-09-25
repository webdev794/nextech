<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAddressChange;
use App\Models\SellerLedgerEntry;
use App\Models\Shop;
use App\Support\Privacy;
use App\Support\SellerOrders;
use App\Support\SellerShipping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seller-scoped order activity (Manage orders + the performance chart),
 * showing only this shop's own line items within each order — never the full
 * order total, other shops' items, or the customer's email or phone. `/admin`
 * remains the only place with full order and customer access.
 */
class SellerOrderController extends Controller
{
    /** Orders shown per page, and the most IDs one search may hold. */
    private const PER_PAGE = 20;

    private const MAX_SEARCH_IDS = 100;

    /**
     * Manage orders: this shop's orders with a Temu-style status (pending /
     * unshipped / shipped / cancelled), "action needed" flags, and filters —
     * a date range (last 30 days by default), sort order, and a search by up
     * to 100 order / goods / SKU / tracking / order item IDs. Only the shop's
     * own lines are shown, never the buyer's email or phone.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in([...SellerOrders::STATUSES, 'all'])],
            'action' => ['sometimes', 'nullable', Rule::in(['buyer_contacted', 'address_change', 'delay_risk'])],
            'range' => ['sometimes', Rule::in(['7', '30', '90', '180', '365', 'custom'])],
            'from' => ['required_if:range,custom', 'nullable', 'date'],
            'to' => ['required_if:range,custom', 'nullable', 'date', 'after_or_equal:from'],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'ship_by'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'q_type' => ['sometimes', Rule::in(['any', 'order', 'goods', 'sku', 'tracking', 'item'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $ids = collect(preg_split('/[\s,]+/', (string) ($data['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($id) => ltrim($id, '#'))->unique()->values();
        if ($ids->count() > self::MAX_SEARCH_IDS) {
            throw ValidationException::withMessages(['q' => 'Search up to '.self::MAX_SEARCH_IDS.' IDs at a time.']);
        }

        $query = Order::query()
            ->whereHas('items', fn ($q) => $q->where('shop_id', $shop->id))
            ->with($this->relations($shop->id));

        if ($ids->isNotEmpty()) {
            // An ID search looks across all dates.
            $type = $data['q_type'] ?? 'any';
            $numeric = $ids->filter(fn ($id) => ctype_digit($id))->map(fn ($id) => (int) $id)->values()->all();
            $query->where(function ($q) use ($type, $ids, $numeric, $shop) {
                if (in_array($type, ['any', 'order'], true) && $numeric) {
                    $q->orWhereIn('id', $numeric);
                }
                if (in_array($type, ['any', 'goods'], true) && $numeric) {
                    $q->orWhereHas('items', fn ($i) => $i->where('shop_id', $shop->id)->whereIn('product_id', $numeric));
                }
                if (in_array($type, ['any', 'item'], true) && $numeric) {
                    $q->orWhereHas('items', fn ($i) => $i->where('shop_id', $shop->id)->whereIn('id', $numeric));
                }
                if (in_array($type, ['any', 'sku'], true)) {
                    $q->orWhereHas('items', fn ($i) => $i->where('shop_id', $shop->id)->whereIn('sku', $ids->all()));
                }
                if (in_array($type, ['any', 'tracking'], true)) {
                    $q->orWhereHas('packages', fn ($p) => $p->where('shop_id', $shop->id)->whereIn('tracking_number', $ids->map(fn ($id) => strtoupper($id))->all()));
                }
            });
        } else {
            $range = $data['range'] ?? '30';
            [$from, $to] = $range === 'custom'
                ? [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay()]
                : [now()->subDays((int) $range)->startOfDay(), now()];
            $query->whereBetween('created_at', [$from, $to]);
        }

        // Status is worked out per order (it depends on packages and timing),
        // so filter and count in PHP — a shop's orders over a date range stay small.
        $rows = $query->latest()->limit(3000)->get()->map(fn (Order $o) => $this->row($o, $shop->id));

        $open = $rows->where('status', '!=', 'cancelled');
        $counts = ['all' => $rows->count()] + collect(SellerOrders::STATUSES)->mapWithKeys(fn ($s) => [$s => $rows->where('status', $s)->count()])->all();
        $actions = [
            'buyer_contacted' => $open->filter(fn ($r) => $r['buyer_contacted_at'])->count(),
            'address_change' => $open->filter(fn ($r) => $r['address_change'])->count(),
            'delay_risk' => $open->filter(fn ($r) => $r['delay_risk'])->count(),
        ];

        $status = $data['status'] ?? 'unshipped';
        $action = $data['action'] ?? null;
        $filtered = $rows
            ->when($status !== 'all' && ! $action && $ids->isEmpty(), fn ($c) => $c->where('status', $status))
            ->when($action === 'buyer_contacted', fn ($c) => $c->filter(fn ($r) => $r['buyer_contacted_at'] && $r['status'] !== 'cancelled'))
            ->when($action === 'address_change', fn ($c) => $c->filter(fn ($r) => $r['address_change'] && $r['status'] !== 'cancelled'))
            ->when($action === 'delay_risk', fn ($c) => $c->filter(fn ($r) => $r['delay_risk']));

        $filtered = match ($data['sort'] ?? 'newest') {
            'oldest' => $filtered->sortBy('created_at'),
            'ship_by' => $filtered->sortBy(fn ($r) => $r['ship_by'] ?? '9999-12-31'),
            default => $filtered->sortByDesc('created_at'),
        };
        $filtered = $filtered->values();
        $page = (int) ($data['page'] ?? 1);

        return response()->json(['data' => [
            // Homepage "Orders in progress" tile.
            'summary' => ['active' => $counts['pending'] + $counts['unshipped'], 'completed' => $rows->where('order_status', 'completed')->count(), 'cancelled' => $counts['cancelled']],
            'counts' => $counts,
            'actions' => $actions,
            'orders' => $filtered->forPage($page, self::PER_PAGE)->values(),
            'total' => $filtered->count(),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'not_found' => $ids->isNotEmpty() ? $this->notFound($ids, $rows) : [],
            'pending_minutes' => SellerOrders::PENDING_MINUTES,
        ]]);
    }

    /** The buyer asked to ship to a new address: the seller shipping it accepts or declines. */
    public function decideAddressChange(Request $request, Order $order, OrderAddressChange $change): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($change->order_id === $order->id && $change->status === 'pending', 404);
        abort_unless($order->items()->where('shop_id', $shop->id)->where('fulfilled_by', 'seller')->exists(), 403, 'NexTech handles address changes for orders it delivers.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'decline'])],
            'note' => ['required_if:decision,decline', 'nullable', 'string', 'max:500'],
        ], ['note.required_if' => 'Tell the buyer why the address can’t be changed.']);

        $order->load('packages');
        if ($data['decision'] === 'approve') {
            abort_unless(SellerOrders::addressChangeable($order), 422, 'The order has already shipped — the address can no longer change.');
            SellerOrders::applyAddressChange($change, $shop->id, $request->user()->id);
        } else {
            $change->update(['status' => 'declined', 'note' => $data['note'], 'decided_by_shop_id' => $shop->id, 'decided_by_user_id' => $request->user()->id, 'decided_at' => now()]);
        }

        return response()->json(['data' => $this->row(Order::with($this->relations($shop->id))->find($order->id), $shop->id)]);
    }

    /** @return array<string|int, mixed> */
    private function relations(int $shopId): array
    {
        return [
            'items' => fn ($q) => $q->where('shop_id', $shopId),
            'packages' => fn ($q) => $q->where('shop_id', $shopId),
            'shopShipping' => fn ($q) => $q->where('shop_id', $shopId),
            'supportThreads:id,order_id,user_id,issue_type,status,last_message_at',
            'addressChanges',
        ];
    }

    /** @return array<string, mixed> */
    private function row(Order $order, int $shopId): array
    {
        $status = SellerOrders::status($order, $shopId);
        $sellerShips = $order->items->contains(fn ($i) => $i->fulfilled_by === 'seller');
        $promise = $order->shopShipping->firstWhere('shop_id', $shopId);
        $address = (array) $order->delivery_address;
        $change = SellerOrders::pendingAddressChange($order);

        return [
            'id' => $order->id,
            'status' => $status,
            'order_status' => $order->status,
            'created_at' => $order->created_at,
            'pending_until' => $status === 'pending' && $order->status !== 'pending_payment' ? SellerOrders::pendingUntil($order) : null,
            'awaiting_payment' => $order->status === 'pending_payment',
            'fulfilled_by' => $sellerShips ? ($order->items->every(fn ($i) => $i->fulfilled_by === 'seller') ? 'seller' : 'mixed') : 'nextech',
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'sku' => $i->sku,
                'product_name' => $i->product_name,
                'variant_label' => $i->variant_label,
                'quantity' => $i->quantity,
                'line_total_cents' => $i->line_total_cents,
                'fulfilled_by' => $i->fulfilled_by,
            ])->values(),
            'ship_by' => $promise?->ship_by?->toDateString(),
            'deliver_from' => $promise?->deliver_from?->toDateString(),
            'deliver_by' => $promise?->deliver_by?->toDateString(),
            // Masked name; the street address only for sellers who write their own
            // courier label (NexTech prints it on the labels it makes). Never email or phone.
            'ship_to' => ['name' => Privacy::maskName($address['name'] ?? null)] + ($sellerShips && request()->user()?->seller?->shop?->fulfillment_mode === 'self'
                ? array_intersect_key($address, array_flip(['line1', 'line2', 'city', 'state', 'postal_code']))
                : array_intersect_key($address, array_flip(['city', 'state']))),
            'packages' => $order->packages->map(fn ($p) => [
                'id' => $p->id, 'carrier' => $p->carrier, 'tracking_number' => $p->tracking_number,
                'tracking_url' => SellerShipping::trackingUrl($p->carrier, $p->tracking_number), 'status' => $p->status, 'shipped_at' => $p->shipped_at,
            ])->values(),
            'buyer_contacted_at' => SellerOrders::buyerContactedAt($order),
            'address_change' => $change && $sellerShips ? ['id' => $change->id, 'address' => ['name' => Privacy::maskName($change->address['name'] ?? null)] + (request()->user()?->seller?->shop?->fulfillment_mode === 'self' ? $change->address : array_intersect_key($change->address, array_flip(['city', 'state']))), 'created_at' => $change->created_at] : null,
            'address_change_nextech' => $change !== null && ! $sellerShips,
            'delay_risk' => SellerOrders::delayRisk($order, $shopId, $status),
            'overdue' => $status === 'unshipped' && $promise !== null && $promise->ship_by->lt(today()),
        ];
    }

    /**
     * Searched IDs that matched nothing, so the seller can spot typos.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $ids
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return list<string>
     */
    private function notFound($ids, $rows): array
    {
        $seen = $rows->flatMap(fn ($r) => [
            (string) $r['id'],
            ...collect($r['items'])->flatMap(fn ($i) => [(string) $i['product_id'], (string) $i['id'], strtoupper((string) $i['sku'])])->all(),
            ...collect($r['packages'])->map(fn ($p) => strtoupper($p['tracking_number']))->all(),
        ])->flip();

        return $ids->reject(fn ($id) => $seen->has($id) || $seen->has(strtoupper($id)))->values()->all();
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
