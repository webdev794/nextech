<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
