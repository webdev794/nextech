<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Support\OrderReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->with(['items', 'riderReview'])
            ->latest()
            ->paginate(20);

        // The handover code is hidden by default; the owning customer sees it so
        // they can read it to the rider at the door.
        $orders->getCollection()->each->makeVisible(['delivery_code', 'delivery_code_expires_at']);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['data' => $order->load('items', 'riderReview')->makeVisible(['delivery_code', 'delivery_code_expires_at'])]);
    }

    /**
     * The customer's rating (1–5) and optional written feedback for the rider who
     * delivered this order, or who they chatted with. One review per order — a
     * repeat call edits it. The comment is stored for the admin only and is
     * never shown back to the rider. The rider's overall rating is recomputed
     * from all their reviews.
     */
    public function storeRiderReview(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->delivery_partner_id !== null, 422, 'This order has no delivery rider.');
        abort_unless(in_array($order->status, ['out_for_delivery', 'completed'], true), 422, 'You can rate the rider once the order is on its way.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'source' => ['sometimes', Rule::in(['delivery', 'chat'])],
        ]);

        $review = $order->riderReview()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'rider_id' => $order->delivery_partner_id,
                'user_id' => $order->user_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'source' => $data['source'] ?? 'delivery',
            ],
        );

        $order->deliveryPartner?->recomputeRiderRating();

        return response()->json(['data' => $review->only(['id', 'rating', 'comment', 'source', 'created_at', 'updated_at'])]);
    }

    /**
     * A printable bill (PDF) for the customer's own order: the shop address, every
     * line with its regular and paid price, the fee breakdown and the total paid.
     *
     * Only issued once the order is a committed, payable order — a card order
     * that has been paid, or a cash-on-delivery order that has been placed
     * (payment is collected on hand-off, so the bill goes out with the order).
     * Not available while a card payment is still pending, or once cancelled.
     */
    public function receipt(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $billable = $order->payment_status === 'paid' || $order->isCashOnDelivery();
        abort_unless($billable && $order->status !== 'cancelled', 403, 'The bill for this order is not available yet.');

        // Orders placed before line-level price snapshots fall back to the
        // product's / variant's current regular price, the same way the cart does.
        return OrderReceipt::pdf($order)->download(OrderReceipt::filename($order));
    }

    /**
     * Switch an unpaid order between card payment and cash on delivery before
     * any money has moved. Lets a customer back out of the card screen without
     * re-doing checkout.
     */
    public function setPaymentMethod(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['card', 'cod'])],
        ]);

        if ($order->payment_status !== 'pending' || ! in_array($order->status, ['pending_payment', 'confirmed'], true)) {
            return response()->json(['message' => 'This order can no longer change its payment method.'], 422);
        }

        if ($validated['payment_method'] === 'cod' && ! Setting::get('cod_enabled', false)) {
            return response()->json(['message' => 'Cash on delivery is not available right now.'], 422);
        }

        $order->update([
            'payment_method' => $validated['payment_method'],
            'status' => $validated['payment_method'] === 'cod' ? 'confirmed' : 'pending_payment',
        ]);

        return response()->json(['data' => $order->load('items')]);
    }

    /**
     * Customer-initiated cancellation, allowed until the order leaves the store
     * (confirmed / packing / ready_for_delivery). A paid order is flagged
     * refund_pending for an admin to process; an unpaid one is voided outright.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if (! $order->canTransitionTo('cancelled')) {
            return response()->json([
                'message' => 'This order can no longer be cancelled. Please contact support.',
            ], 422);
        }

        $paymentStatus = match (true) {
            $order->wasFullyCoveredByGiftCard() => 'refunded',
            $order->payment_status === 'paid' => 'refund_pending',
            default => 'cancelled',
        };

        $order->update(['status' => 'cancelled', 'payment_status' => $paymentStatus, 'cancelled_by' => 'customer']);
        $order->restoreGiftCardRedemptions();

        return response()->json(['data' => $order->load('items')]);
    }
}
