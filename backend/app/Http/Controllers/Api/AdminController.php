<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\LabelRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\PayoutRequest;
use App\Models\Product;
use App\Models\RiderApplication;
use App\Models\RiderPayoutRequest;
use App\Models\Seller;
use App\Models\RiderReview;
use App\Models\SiteFeedback;
use App\Models\SupportThread;
use App\Models\User;
use App\Support\CustomerNames;
use App\Support\Market;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        // One market (currency) at a time — the admin's currency switch.
        $market = Market::fromRequest($request);
        $home = $market;
        $ordersByStatus = Order::query()->where('market', $market)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Money figures are home-market (USD) only — other markets' orders are
        // in their own currency and can't be added in.
        $revenue = (int) Order::where('market', $home)->where('payment_status', 'paid')->sum('total_cents');
        $paidOrders = (int) Order::where('market', $market)->where('payment_status', 'paid')->count();

        // Total discount handed out: the frozen regular price minus the price
        // actually billed, across every order line that carries a snapshot.
        $discount = (int) OrderItem::query()
            ->whereNotNull('compare_at_price_cents')
            ->whereHas('order', fn ($q) => $q->where('market', $market))
            ->whereColumn('compare_at_price_cents', '>', 'unit_price_cents')
            ->sum(DB::raw('(compare_at_price_cents - unit_price_cents) * quantity'));

        // "Refunded" covers everything actually given back to a customer —
        // a Stripe refund and store credit (gift card) both count.
        $refunded = (int) Order::where('market', $home)->sum('refunded_amount_cents') + ($market === Market::home() ? (int) GiftCard::sum('initial_cents') : 0);

        // Every order where money was ever collected, refunded or not — the
        // whole pie the Payments-vs-refunds chart splits into kept vs given back.
        $grossCollected = (int) Order::where('market', $home)->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded', 'refund_pending'])->sum('total_cents');

        return response()->json([
            'data' => [
                'orders_by_status' => $ordersByStatus,
                'orders_total' => (int) $ordersByStatus->sum(),
                'awaiting_fulfilment' => (int) $ordersByStatus->only(['confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery'])->sum(),
                'revenue_cents' => $revenue,
                'market' => $market,
                'currency' => Market::currency($market),
                'avg_order_cents' => $paidOrders ? intdiv($revenue, $paidOrders) : 0,
                'discount_cents' => $discount,
                'cod_orders' => (int) Order::where('market', $market)->where('payment_method', 'cod')->count(),
                'refunded_cents' => $refunded,
                'gross_collected_cents' => $grossCollected,
                'customers' => User::where('is_admin', false)->count(),
                'products' => Product::count(),
                'low_stock' => Product::where('inventory_quantity', '<=', 5)->count(),
            ],
        ]);
    }

    /**
     * Standing issues the admin should keep an eye on: riders sitting on COD
     * cash from a day other than today, and recent (last 7 days) negative
     * feedback from a delivery or chat rating. Feeds the bell in the top bar.
     */
    public function notifications(): JsonResponse
    {
        // Paid orders sitting unpacked — every new order lands here first.
        $awaitingPacking = Order::query()->where('status', 'confirmed')
            ->with('user:id,name,email')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Order $o) => [
                'order_id' => $o->id,
                'total_cents' => $o->total_cents,
                'customer' => $o->user?->name ?? $o->user?->email,
                'at' => $o->created_at,
            ]);

        // A rider reported the customer refused to pay for a COD delivery —
        // the order was auto-cancelled, and the bagged items are still with
        // the rider until the store confirms they're back.
        $refusedCod = Order::query()->where('status', 'cancelled')->where('cancelled_by', 'rider')
            ->whereNull('items_returned_at')
            ->latest()
            ->limit(20)
            ->get(['id', 'cancel_reason', 'updated_at'])
            ->map(fn (Order $o) => ['order_id' => $o->id, 'reason' => $o->cancel_reason, 'at' => $o->updated_at]);

        $cashOverdue = User::query()->where('is_rider', true)->get()
            ->map(fn (User $rider) => ['rider' => $rider, 'cents' => $rider->codHoldingCents(), 'since' => $rider->codHoldingSince()])
            ->filter(fn (array $row) => $row['cents'] > 0 && $row['since'] !== null && ! $row['since']->isToday())
            ->sortBy(fn (array $row) => $row['since'])
            ->map(fn (array $row) => [
                'rider_id' => $row['rider']->id,
                'rider_name' => $row['rider']->name,
                'holding_cents' => $row['cents'],
                'since' => $row['since'],
            ])
            ->values();

        $since = now()->subDays(7);

        // A busy week can easily produce far more of these than fit in a
        // dropdown — sample the latest few for display, but report the real
        // total separately so "+N more" isn't just guessing from a truncated
        // query.
        $sampleSize = 10;

        $riderFeedbackQuery = RiderReview::query()->where('rating', '<=', 2)->where('created_at', '>=', $since);
        $chatFeedbackQuery = SupportThread::query()->whereNotNull('rating')->where('rating', '<=', 2)->where('rated_at', '>=', $since);
        $siteFeedbackQuery = SiteFeedback::query()->where('rating', '<=', 2)->where('created_at', '>=', $since);
        $negativeFeedbackTotal = $riderFeedbackQuery->clone()->count() + $chatFeedbackQuery->clone()->count() + $siteFeedbackQuery->clone()->count();

        $riderFeedback = $riderFeedbackQuery->with('rider:id,name')->latest()->limit($sampleSize)->get()
            ->map(fn (RiderReview $r) => [
                'source' => 'delivery',
                'order_id' => $r->order_id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'rider_name' => $r->rider?->name,
                'at' => $r->created_at,
            ]);

        $chatFeedback = $chatFeedbackQuery->latest('rated_at')->limit($sampleSize)->get()
            ->map(fn (SupportThread $t) => [
                'source' => 'chat',
                'order_id' => $t->order_id,
                'thread_id' => $t->id,
                'rating' => $t->rating,
                'comment' => $t->rating_comment,
                'rider_name' => null,
                'at' => $t->rated_at,
            ]);

        $siteFeedback = $siteFeedbackQuery->with('user:id,name')->latest()->limit($sampleSize)->get()
            ->map(fn (SiteFeedback $f) => [
                'source' => 'site',
                'order_id' => null,
                'rating' => $f->rating,
                'comment' => null,
                'rider_name' => $f->user?->name,
                'at' => $f->created_at,
            ]);

        $negativeFeedback = $riderFeedback->concat($chatFeedback)->concat($siteFeedback)->sortByDesc('at')->take($sampleSize)->values();

        // Every recent rating, good or bad — not a standing "needs attention"
        // list (that's negative_feedback above), just enough for the admin
        // console to pop a quick "here's what customers just said" toast,
        // including the positive ones, then let it fade away on its own.
        $allRiderRatings = RiderReview::query()->with('rider:id,name')->latest()->limit($sampleSize)->get()
            ->map(fn (RiderReview $r) => [
                'source' => 'delivery',
                'order_id' => $r->order_id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'rider_name' => $r->rider?->name,
                'at' => $r->created_at,
            ]);
        $allChatRatings = SupportThread::query()->whereNotNull('rating')->latest('rated_at')->limit($sampleSize)->get()
            ->map(fn (SupportThread $t) => [
                'source' => 'chat',
                'order_id' => $t->order_id,
                'thread_id' => $t->id,
                'rating' => $t->rating,
                'comment' => $t->rating_comment,
                'rider_name' => null,
                'at' => $t->rated_at,
            ]);
        $allSiteRatings = SiteFeedback::query()->with('user:id,name')->latest()->limit($sampleSize)->get()
            ->map(fn (SiteFeedback $f) => [
                'source' => 'site',
                'order_id' => null,
                'rating' => $f->rating,
                'comment' => null,
                'rider_name' => $f->user?->name,
                'at' => $f->created_at,
            ]);
        $recentRatings = $allRiderRatings->concat($allChatRatings)->concat($allSiteRatings)->sortByDesc('at')->take($sampleSize)->values();

        $giftCardsQuery = GiftCard::query()->where('created_at', '>=', $since);
        $refundsQuery = OrderRefund::query()->where('created_at', '>=', $since);
        $financialActivityTotal = $giftCardsQuery->clone()->count() + $refundsQuery->clone()->count();

        $giftCardsIssued = $giftCardsQuery->with('issuedBy:id,name')->latest()->limit($sampleSize)->get()
            ->map(fn (GiftCard $g) => [
                'type' => 'gift_card',
                'order_id' => $g->order_id,
                'amount_cents' => $g->initial_cents,
                'reason' => $g->reason,
                'issued_by_name' => $g->issuedBy?->name,
                'at' => $g->created_at,
            ]);

        $refundsIssued = $refundsQuery->with('creator:id,name')->latest()->limit($sampleSize)->get()
            ->map(fn (OrderRefund $r) => [
                'type' => 'refund',
                'order_id' => $r->order_id,
                'amount_cents' => $r->amount_cents,
                'reason' => $r->reason,
                'issued_by_name' => $r->creator?->name,
                'at' => $r->created_at,
            ]);

        $financialActivity = $giftCardsIssued->concat($refundsIssued)->sortByDesc('at')->take($sampleSize)->values();

        $payoutRequests = PayoutRequest::query()
            ->where('status', 'pending')
            ->with('shop:id,name,seller_id')
            ->oldest()
            ->get()
            ->map(fn (PayoutRequest $r) => [
                'id' => $r->id,
                'seller_id' => $r->shop?->seller_id,
                'shop_name' => $r->shop?->name,
                'amount_cents' => $r->amount_cents,
                'at' => $r->created_at,
            ]);

        // Sellers waiting on a NexTech label to be uploaded.
        $labelRequests = LabelRequest::query()
            ->where('status', 'requested')
            ->with('shop:id,name')
            ->oldest()
            ->get()
            ->map(fn (LabelRequest $r) => [
                'id' => $r->id,
                'order_id' => $r->order_id,
                'shop_name' => $r->shop?->name,
                'at' => $r->created_at,
            ]);

        $riderPayoutRequests = RiderPayoutRequest::query()
            ->where('status', 'pending')
            ->with('rider:id,name')
            ->oldest()
            ->get()
            ->map(fn (RiderPayoutRequest $r) => [
                'id' => $r->id,
                'rider_id' => $r->user_id,
                'rider_name' => $r->rider?->name,
                'amount_cents' => $r->amount_cents,
                'at' => $r->created_at,
            ]);

        $riderApplications = RiderApplication::query()
            ->where('status', 'pending')
            ->with(['user:id,name', 'store:id,name'])
            ->oldest()
            ->get()
            ->map(fn (RiderApplication $a) => [
                'id' => $a->id,
                'name' => $a->user?->name,
                'store_name' => $a->store?->name,
                'at' => $a->created_at,
            ]);

        // New (or resubmitted) seller applications waiting for review.
        $sellerApplications = Seller::query()
            ->where('status', 'pending')
            ->with(['shop:id,seller_id,name', 'user:id,name'])
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (Seller $s) => [
                'id' => $s->id,
                'name' => $s->shop?->name ?? $s->company_name ?? $s->user?->name,
                'at' => $s->submitted_at ?? $s->created_at,
            ]);

        return response()->json(['data' => [
            'seller_applications' => $sellerApplications,
            'awaiting_packing' => $awaitingPacking,
            'payout_requests' => $payoutRequests,
            'label_requests' => $labelRequests,
            'rider_payout_requests' => $riderPayoutRequests,
            'rider_applications' => $riderApplications,
            'refused_cod' => $refusedCod,
            'cash_overdue' => $cashOverdue,
            'negative_feedback' => $negativeFeedback,
            'negative_feedback_total' => $negativeFeedbackTotal,
            'financial_activity' => $financialActivity,
            'financial_activity_total' => $financialActivityTotal,
            'recent_ratings' => $recentRatings,
        ]]);
    }

    /**
     * Orders bucketed over time for the dashboard charts. Grouping is done in
     * PHP so it works the same on SQLite (tests) and MySQL (runtime).
     */
    public function ordersTimeseries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bucket' => ['sometimes', 'in:day,week,month'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'tz' => ['sometimes', 'nullable', 'string'],
        ]);

        $tz = $this->resolveTz($validated['tz'] ?? null);
        $bucket = $validated['bucket'] ?? 'day';
        $to = isset($validated['to']) ? Carbon::parse($validated['to'], $tz)->endOfDay() : now($tz)->endOfDay();

        $span = ['day' => 13, 'week' => 11, 'month' => 11][$bucket];
        $from = isset($validated['from']) ? Carbon::parse($validated['from'], $tz) : match ($bucket) {
            'day' => $to->copy()->subDays($span),
            'week' => $to->copy()->subWeeks($span),
            'month' => $to->copy()->subMonths($span),
        };

        // Never build more than this many buckets (guards a huge custom range).
        $maxBuckets = ['day' => 186, 'week' => 130, 'month' => 60][$bucket];
        $earliest = match ($bucket) {
            'day' => $to->copy()->subDays($maxBuckets - 1),
            'week' => $to->copy()->subWeeks($maxBuckets - 1),
            'month' => $to->copy()->subMonths($maxBuckets - 1),
        };
        if ($from->lt($earliest)) {
            $from = $earliest;
        }

        $from = match ($bucket) {
            'day' => $from->copy()->startOfDay(),
            'week' => $from->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $from->copy()->startOfMonth(),
        };

        $keyFor = fn (Carbon $d): string => match ($bucket) {
            'day' => $d->format('Y-m-d'),
            'week' => $d->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'month' => $d->format('Y-m-01'),
        };
        $labelFor = fn (Carbon $d): string => $bucket === 'month' ? $d->format('M Y') : $d->format('M j');

        $orders = Order::query()
            ->where('market', Market::fromRequest($request))
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->get(['created_at', 'status', 'payment_status', 'payment_method', 'total_cents']);

        $agg = [];
        foreach ($orders as $order) {
            $key = $keyFor($order->created_at->copy()->setTimezone($tz));
            $agg[$key] ??= ['orders' => 0, 'paid_orders' => 0, 'revenue_cents' => 0, 'refunded_cents' => 0];
            $agg[$key]['orders']++;
            if ($order->payment_status === 'paid') {
                $agg[$key]['paid_orders']++;
                $agg[$key]['revenue_cents'] += (int) $order->total_cents;
            }
        }

        // Refunds and gift cards issued in this window — bucketed by when
        // they were issued, not when the order was placed, so the "Refunds"
        // line reflects money actually given back on each day/week/month.
        $refundEvents = OrderRefund::query()
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->get(['created_at', 'amount_cents'])
            ->concat(
                GiftCard::query()
                    ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
                    ->get(['created_at', 'initial_cents as amount_cents'])
            );
        foreach ($refundEvents as $event) {
            $key = $keyFor($event->created_at->copy()->setTimezone($tz));
            $agg[$key] ??= ['orders' => 0, 'paid_orders' => 0, 'revenue_cents' => 0, 'refunded_cents' => 0];
            $agg[$key]['refunded_cents'] += (int) $event->amount_cents;
        }

        $series = [];
        $cursor = $from->copy();
        while ($cursor <= $to && count($series) < $maxBuckets) {
            $key = $keyFor($cursor);
            $series[] = [
                'period' => $key,
                'label' => $labelFor($cursor),
                'orders' => $agg[$key]['orders'] ?? 0,
                'paid_orders' => $agg[$key]['paid_orders'] ?? 0,
                'revenue_cents' => $agg[$key]['revenue_cents'] ?? 0,
                'refunded_cents' => $agg[$key]['refunded_cents'] ?? 0,
            ];
            match ($bucket) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
            };
        }

        return response()->json([
            'data' => [
                'bucket' => $bucket,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'series' => $series,
                'by_status' => $orders->countBy('status'),
                'by_payment_method' => $orders->countBy('payment_method'),
                'totals' => [
                    'orders' => array_sum(array_column($series, 'orders')),
                    'paid_orders' => array_sum(array_column($series, 'paid_orders')),
                    'revenue_cents' => array_sum(array_column($series, 'revenue_cents')),
                    'refunded_cents' => array_sum(array_column($series, 'refunded_cents')),
                ],
            ],
        ]);
    }

    /**
     * One period-over-period comparison for the dashboard. `preset` picks the
     * span (day / two_day / week / month / six_month / year) or `custom` with a
     * rolling `days` window. The current window runs to "now"; the previous
     * window is the FULL prior period (all of last month, not just its first N
     * days) so two whole months can be read side by side. Returns the two
     * window totals and a `partial` flag for the still-running current period.
     */
    public function ordersCompare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset' => ['sometimes', 'in:day,two_day,week,month,six_month,year,custom'],
            'days' => ['required_if:preset,custom', 'integer', 'min:1', 'max:730'],
            'tz' => ['sometimes', 'nullable', 'string'],
        ]);

        $preset = $validated['preset'] ?? 'month';
        $days = isset($validated['days']) ? (int) $validated['days'] : null;
        $now = now($this->resolveTz($validated['tz'] ?? null));

        [$curStart, $curFullEnd, $prevStart, $prevEnd, $unit, $curLabel, $prevLabel] = $this->comparePreset($preset, $days, $now);

        $curTotals = ['orders' => 0, 'paid_orders' => 0, 'revenue_cents' => 0];
        $prevTotals = $curTotals;

        Order::query()->where('market', Market::fromRequest($request))
            ->where('created_at', '>=', $prevStart->copy()->utc())
            ->where('created_at', '<', $now->copy()->utc())
            ->get(['created_at', 'payment_status', 'total_cents'])
            ->each(function (Order $order) use (
                &$curTotals, &$prevTotals, $curStart, $now, $prevStart, $prevEnd
            ): void {
                $moment = $order->created_at;
                $paid = $order->payment_status === 'paid';
                $revenue = $paid ? (int) $order->total_cents : 0;

                if ($moment >= $curStart && $moment < $now) {
                    $curTotals['orders']++;
                    $curTotals['paid_orders'] += $paid ? 1 : 0;
                    $curTotals['revenue_cents'] += $revenue;
                } elseif ($moment >= $prevStart && $moment < $prevEnd) {
                    $prevTotals['orders']++;
                    $prevTotals['paid_orders'] += $paid ? 1 : 0;
                    $prevTotals['revenue_cents'] += $revenue;
                }
            });

        return response()->json(['data' => [
            'preset' => $preset,
            'days' => $preset === 'custom' ? $days : null,
            'bucket' => $unit,
            'partial' => $now->lt($curFullEnd),
            'current' => ['label' => $curLabel, 'from' => $curStart->toIso8601String(), 'to' => $now->toIso8601String(), ...$curTotals],
            'previous' => ['label' => $prevLabel, 'from' => $prevStart->toIso8601String(), 'to' => $prevEnd->toIso8601String(), ...$prevTotals],
        ]]);
    }

    /**
     * An orders-by-weekday-and-hour grid for the last 90 days, for the
     * dashboard activity heatmap.
     */
    public function ordersInsights(Request $request): JsonResponse
    {
        $validated = $request->validate(['tz' => ['sometimes', 'nullable', 'string']]);
        $tz = $this->resolveTz($validated['tz'] ?? null);
        $since = now($tz)->subDays(90)->startOfDay();
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));
        $peak = 0;

        Order::query()->where('market', Market::fromRequest($request))
            ->where('created_at', '>=', $since->copy()->utc())
            ->get(['created_at'])
            ->each(function (Order $order) use (&$matrix, &$peak, $tz): void {
                $local = $order->created_at->copy()->setTimezone($tz);
                $row = (int) $local->dayOfWeekIso - 1; // Mon=0 .. Sun=6
                $col = (int) $local->format('G');       // 0..23
                $matrix[$row][$col]++;
                $peak = max($peak, $matrix[$row][$col]);
            });

        return response()->json(['data' => [
            'activity' => [
                'rows' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'matrix' => $matrix,
                'peak' => $peak,
                'since' => $since->toDateString(),
            ],
        ]]);
    }

    /**
     * The dashboard charts bucket by the admin's own timezone rather than the
     * server's (UTC), so "today" matches their clock. Best-effort: an
     * unrecognised or missing value quietly falls back to UTC instead of
     * failing the whole request — a browser reporting an odd timezone string
     * shouldn't take the charts down.
     */
    private function resolveTz(?string $tz): string
    {
        if (! $tz) {
            return 'UTC';
        }

        try {
            new \DateTimeZone($tz);

            return $tz;
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string, 5: string, 6: string}
     *                                                                                            [currentStart, currentFullEnd, previousStart, previousEnd, bucketUnit, currentLabel, previousLabel]
     */
    private function comparePreset(string $preset, ?int $days, Carbon $now): array
    {
        return match ($preset) {
            'day' => [
                $now->copy()->startOfDay(), $now->copy()->startOfDay()->addDay(),
                $now->copy()->startOfDay()->subDay(), $now->copy()->startOfDay(),
                'hour', 'Today', 'Yesterday',
            ],
            'two_day' => [
                $now->copy()->subDays(2), $now->copy(),
                $now->copy()->subDays(4), $now->copy()->subDays(2),
                'hour', 'Last 2 days', 'Previous 2 days',
            ],
            'week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->startOfWeek(Carbon::MONDAY)->addWeek(),
                $now->copy()->startOfWeek(Carbon::MONDAY)->subWeek(), $now->copy()->startOfWeek(Carbon::MONDAY),
                'day', 'This week', 'Last week',
            ],
            'month' => [
                $now->copy()->startOfMonth(), $now->copy()->startOfMonth()->addMonthNoOverflow(),
                $now->copy()->startOfMonth()->subMonthNoOverflow(), $now->copy()->startOfMonth(),
                'day', 'This month', 'Last month',
            ],
            'six_month' => [
                $now->copy()->startOfMonth()->subMonthsNoOverflow(5), $now->copy()->startOfMonth()->addMonthNoOverflow(),
                $now->copy()->startOfMonth()->subMonthsNoOverflow(11), $now->copy()->startOfMonth()->subMonthsNoOverflow(5),
                'month', 'Last 6 months', 'Previous 6 months',
            ],
            'year' => [
                $now->copy()->startOfYear(), $now->copy()->startOfYear()->addYear(),
                $now->copy()->startOfYear()->subYear(), $now->copy()->startOfYear(),
                'month', 'This year', 'Last year',
            ],
            'custom' => [
                $now->copy()->subDays($days), $now->copy(),
                $now->copy()->subDays($days * 2), $now->copy()->subDays($days),
                $days <= 2 ? 'hour' : ($days <= 92 ? 'day' : 'month'),
                "Last {$days} days",
                "Previous {$days} days",
            ],
        };
    }

    public function customers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $customers = User::query()
            ->where('is_admin', false)
            ->withCount('orders')
            ->withSum(['orders as spent_cents' => fn ($query) => $query->where('payment_status', 'paid')], 'total_cents')
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        $names = CustomerNames::map();

        return response()->json([
            'data' => $customers->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $names[$user->id] ?? CustomerNames::base($user),
                'email' => $user->email,
                'phone' => $user->phone,
                'is_rider' => (bool) $user->is_rider,
                'orders_count' => $user->orders_count,
                'spent_cents' => (int) ($user->spent_cents ?? 0),
                'joined_at' => $user->created_at,
            ])->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function customer(User $user): JsonResponse
    {
        if ($user->is_admin) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => CustomerNames::map()[$user->id] ?? CustomerNames::base($user),
                'email' => $user->email,
                'phone' => $user->phone,
                'is_rider' => (bool) $user->is_rider,
                'joined_at' => $user->created_at,
                'addresses' => $user->addresses()->latest()->get(),
                'orders' => $user->orders()->with('items')->latest()->get(),
            ],
        ]);
    }

    public function updateCustomer(Request $request, User $user): JsonResponse
    {
        if ($user->is_admin) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate(['is_rider' => ['required', 'boolean']]);

        // Promoting from the quick toggle also puts the rider "on shift".
        $user->forceFill([
            'is_rider' => $validated['is_rider'],
            'rider_is_active' => $validated['is_rider'],
            'rider_since' => $validated['is_rider'] ? ($user->rider_since ?? now()) : $user->rider_since,
        ])->save();

        return response()->json(['data' => ['id' => $user->id, 'is_rider' => (bool) $user->is_rider]]);
    }
}
