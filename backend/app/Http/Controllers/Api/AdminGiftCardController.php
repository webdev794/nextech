<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\SupportThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminGiftCardController extends Controller
{
    /**
     * Issue store credit against a paid order — e.g. items confirmed missing on a
     * cash delivery. The card is bound to the order's customer; the code + a
     * one-time password are returned and posted into the support thread.
     */
    public function issue(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'item_ids' => ['sometimes', 'array'],
            'item_ids.*' => ['integer'],
            'amount_cents' => ['sometimes', 'integer', 'min:1'],
            'support_thread_id' => ['sometimes', 'nullable', 'integer', 'exists:support_threads,id'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        if (! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
            return response()->json(['message' => 'Store credit can only be issued against a paid order.'], 422);
        }

        if ($order->giftCards()->exists()) {
            return response()->json(['message' => 'Store credit has already been issued for this order.'], 422);
        }

        $items = ! empty($data['item_ids'])
            ? $order->items()->whereIn('id', $data['item_ids'])->get()
            : collect();

        $returned = (int) $order->refunded_amount_cents + $order->giftCardRefundedCents();
        $room = max(0, (int) $order->total_cents - $returned);

        // Every item on the order is being returned — the full remaining
        // balance (tax, delivery, handling included), not just the items'
        // combined price.
        $allItemsSelected = $items->isNotEmpty() && $items->count() === $order->items()->count();

        $amount = match (true) {
            $allItemsSelected => $room,
            $items->isNotEmpty() => (int) $items->sum('line_total_cents'),
            default => (int) ($data['amount_cents'] ?? 0),
        };

        if ($amount < 1 || $amount > $room) {
            return response()->json([
                'message' => 'Amount must be between $0.01 and $'.number_format($room / 100, 2)
                    .' (already returned $'.number_format($returned / 100, 2).').',
            ], 422);
        }

        $pin = GiftCard::makePin();
        $card = GiftCard::create([
            'code' => GiftCard::makeCode(),
            'pin_hash' => Hash::make($pin),
            'user_id' => $order->user_id,
            'issued_by' => $request->user()->id,
            'support_thread_id' => $data['support_thread_id'] ?? null,
            'order_id' => $order->id,
            'initial_cents' => $amount,
            'balance_cents' => $amount,
            'is_active' => true,
            'reason' => $data['reason'] ?? ($items->isNotEmpty()
                ? 'Missing: '.$items->pluck('product_name')->implode(', ')
                : null),
        ]);

        if (! empty($data['support_thread_id'])) {
            $thread = SupportThread::find($data['support_thread_id']);
            $money = '$'.number_format($amount / 100, 2);
            $what = $items->isNotEmpty()
                ? ' for the missing item(s): '.$items->pluck('product_name')->implode(', ')
                : '';
            $thread?->post(
                null,
                "Store credit {$money} issued{$what}.\nGift card: {$card->code}\nPassword: {$pin}\n"
                    .'Enter both at checkout on your next order to use the balance.',
                isStaff: true,
                system: true,
            );
            // The admin's own typed reason (distinct from the auto-derived
            // missing-item list above) is for staff reference only.
            if (! empty($data['reason'])) {
                $thread?->post(null, "Reason: {$data['reason']}", isStaff: true, system: true, internal: true);
            }
        }

        return response()->json(['data' => [
            'code' => $card->code,
            'pin' => $pin,
            'amount_cents' => $amount,
            'order_id' => $order->id,
        ]], 201);
    }

    /**
     * Apply a gift card the customer already has (issued earlier, from a past
     * order) to a different order that's still open — e.g. they ask in chat
     * because they don't know how to enter the code themselves at checkout.
     * Ownership is enforced: the card must belong to the order's own customer.
     */
    public function applyToOrder(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'gift_card_code' => ['required', 'string', 'max:32'],
            'support_thread_id' => ['sometimes', 'nullable', 'integer', 'exists:support_threads,id'],
        ]);

        if ($order->payment_status !== 'pending') {
            return response()->json(['message' => 'This order is not open — it has already been paid, refunded, or cancelled.'], 422);
        }

        $result = DB::transaction(function () use ($data, $order) {
            $card = GiftCard::where('code', strtoupper(trim($data['gift_card_code'])))
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (! $card || ! $card->isSpendable()) {
                return ['error' => 'That gift card doesn\'t belong to this customer, or has no balance left.'];
            }

            $applied = min($card->balance_cents, $order->total_cents);
            if ($applied <= 0) {
                return ['error' => 'Nothing to apply — this order is already fully covered.'];
            }

            $card->redemptions()->create(['order_id' => $order->id, 'amount_cents' => $applied]);
            $card->decrement('balance_cents', $applied);
            if ($card->fresh()->balance_cents <= 0) {
                $card->update(['is_active' => false]);
            }

            $newTotal = $order->total_cents - $applied;
            $order->update([
                'gift_card_discount_cents' => $order->gift_card_discount_cents + $applied,
                'total_cents' => $newTotal,
                'payment_status' => $newTotal <= 0 ? 'paid' : $order->payment_status,
            ]);

            return ['card' => $card, 'applied' => $applied, 'order' => $order->fresh()];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        if (! empty($data['support_thread_id'])) {
            $money = '$'.number_format($result['applied'] / 100, 2);
            SupportThread::find($data['support_thread_id'])?->post(
                null,
                "Applied {$money} from gift card {$result['card']->code} to order #{$order->id}.".
                    ($result['card']->balance_cents > 0 ? ' Remaining balance: $'.number_format($result['card']->balance_cents / 100, 2).'.' : ''),
                isStaff: true,
                system: true,
            );
        }

        return response()->json(['data' => [
            'order' => $result['order'],
            'applied_cents' => $result['applied'],
            'card_balance_cents' => $result['card']->balance_cents,
        ]]);
    }
}
