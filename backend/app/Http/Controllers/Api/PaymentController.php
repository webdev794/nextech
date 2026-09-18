<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StripeEvent;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

class PaymentController extends Controller
{
    public function intent(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if ($order->isCashOnDelivery()) {
            return response()->json(['message' => 'This order is cash on delivery.'], 422);
        }

        if (! config('services.stripe.secret')) {
            return response()->json([
                'message' => 'Stripe is not configured. Add STRIPE_SECRET to the backend environment.',
            ], 503);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['data' => ['order_id' => $order->id, 'payment_status' => 'paid']]);
        }

        if ($order->status === 'cancelled' || $order->payment_status === 'cancelled') {
            return response()->json(['message' => 'This order has been cancelled.'], 422);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            // Always attach the customer so the shopper can pay with a saved
            // card, or tick "save this card" and have it kept for next time.
            $intent = $order->stripe_payment_intent_id
                ? $stripe->paymentIntents->retrieve($order->stripe_payment_intent_id)
                : $stripe->paymentIntents->create([
                    'amount' => $order->total_cents,
                    'currency' => 'usd',
                    'customer' => $this->customerId($request->user(), $stripe),
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => ['order_id' => (string) $order->id],
                ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe PaymentIntent failed', ['order_id' => $order->id, 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Payment could not be set up. Please try again.'], 502);
        }

        if ($order->stripe_payment_intent_id !== $intent->id) {
            $order->update(['stripe_payment_intent_id' => $intent->id]);
        }

        // Fallback for a missed or delayed webhook: if Stripe has already settled
        // the intent, confirm the order now from the authoritative source.
        if ($intent->status === 'succeeded') {
            DB::transaction(function () use ($order): void {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();
                $this->markPaid($locked);
                $order->setRawAttributes($locked->getAttributes(), true);
            });

            Log::info('Order reconciled from Stripe on intent request', ['order_id' => $order->id]);

            return response()->json(['data' => ['order_id' => $order->id, 'payment_status' => 'paid']]);
        }

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'client_secret' => $intent->client_secret,
            ],
        ]);
    }

    /**
     * Admin-triggered Stripe refund. Full or partial: a bare call refunds the
     * remaining balance; `item_ids` sums those line totals; `amount_cents`
     * overrides. Refunds stack until they reach the order total. When linked to
     * a support thread, a system note is added to the conversation.
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'amount_cents' => ['sometimes', 'integer', 'min:1'],
            'item_ids' => ['sometimes', 'array'],
            'item_ids.*' => ['integer'],
            'support_thread_id' => ['sometimes', 'nullable', 'integer', 'exists:support_threads,id'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        if (! in_array($order->payment_status, ['refund_pending', 'paid', 'partially_refunded'], true)) {
            return response()->json(['message' => 'This order is not in a refundable state.'], 422);
        }

        if (! $order->stripe_payment_intent_id) {
            return response()->json(['message' => 'This order has no Stripe payment to refund (cash on delivery?). Mark it refunded manually.'], 422);
        }

        $remaining = $order->refundableRemainingCents();
        if ($remaining <= 0) {
            return response()->json(['message' => 'This order is already fully refunded.'], 422);
        }

        // Every item on the order is being returned — the full remaining
        // balance (tax, delivery, handling included), not just the items'
        // combined price.
        $allItemsSelected = ! empty($validated['item_ids'])
            && count($validated['item_ids']) === $order->items()->count()
            && $order->items()->whereIn('id', $validated['item_ids'])->count() === $order->items()->count();

        $amount = match (true) {
            isset($validated['amount_cents']) => (int) $validated['amount_cents'],
            $allItemsSelected => $remaining,
            ! empty($validated['item_ids']) => (int) $order->items()->whereIn('id', $validated['item_ids'])->sum('line_total_cents'),
            default => $remaining,
        };

        if ($amount <= 0 || $amount > $remaining) {
            return response()->json(['message' => "Refund amount must be between \$0.01 and \${$this->dollars($remaining)}."], 422);
        }

        if (! config('services.stripe.secret')) {
            return response()->json(['message' => 'Stripe is not configured.'], 503);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            $refund = $stripe->refunds->create([
                'payment_intent' => $order->stripe_payment_intent_id,
                'amount' => $amount,
            ]);
        } catch (ApiErrorException $exception) {
            if ($exception->getStripeCode() === 'charge_already_refunded') {
                $order->update(['payment_status' => 'refunded', 'refunded_amount_cents' => $order->total_cents]);

                return response()->json(['data' => $this->orderPayload($order)]);
            }

            Log::error('Stripe refund failed', ['order_id' => $order->id, 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Stripe declined the refund. Check the dashboard.'], 502);
        }

        $refundedTotal = $order->refunded_amount_cents + $amount;

        $order->refunds()->create([
            'support_thread_id' => $validated['support_thread_id'] ?? null,
            'created_by' => $request->user()->id,
            'amount_cents' => $amount,
            'reason' => $validated['reason'] ?? null,
            'stripe_refund_id' => $refund->id,
        ]);

        $order->update([
            'refunded_amount_cents' => $refundedTotal,
            'stripe_refund_id' => $refund->id,
            'payment_status' => $refundedTotal >= $order->total_cents ? 'refunded' : 'partially_refunded',
        ]);

        if (! empty($validated['support_thread_id'])) {
            $thread = SupportThread::find($validated['support_thread_id']);
            $thread?->post(null, 'Refund of $'.$this->dollars($amount).' issued.', isStaff: true, system: true);
            // The admin's reason is for later staff reference only — it never
            // reaches the customer's own view of this conversation.
            if (! empty($validated['reason'])) {
                $thread?->post(null, "Refund reason: {$validated['reason']}", isStaff: true, system: true, internal: true);
            }
        }

        Log::info('Order refunded via Stripe', ['order_id' => $order->id, 'amount' => $amount, 'refund' => $refund->id]);

        return response()->json(['data' => $this->orderPayload($order)]);
    }

    private function orderPayload(Order $order): Order
    {
        return $order->fresh(['items', 'user:id,name,email', 'refunds']);
    }

    /** Get-or-create the Stripe Customer for a user, so a card can be saved. */
    private function customerId(User $user, StripeClient $stripe): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    private function dollars(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    public function webhook(Request $request): JsonResponse
    {
        if (! config('services.stripe.webhook_secret')) {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json([
                'message' => 'Stripe webhook processing is not configured.',
            ], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
        } catch (\Throwable $exception) {
            Log::warning('Invalid Stripe webhook', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Invalid webhook.'], 400);
        }

        if (StripeEvent::whereKey($event->id)->exists()) {
            Log::info('Duplicate Stripe event ignored', ['event' => $event->id, 'type' => $event->type]);

            return response()->json(['received' => true]);
        }

        try {
            DB::transaction(function () use ($event): void {
                // Recording the event id first; the primary key is the last-resort
                // guard against two deliveries racing past the check above.
                StripeEvent::create([
                    'id' => $event->id,
                    'type' => $event->type,
                    'processed_at' => now(),
                ]);

                $this->applyPaymentEvent($event);
            });
        } catch (UniqueConstraintViolationException) {
            Log::info('Duplicate Stripe event ignored', ['event' => $event->id, 'type' => $event->type]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Move the order through its payment lifecycle. Transitions are guarded so
     * that out-of-order deliveries (a late failure after a success, a cancel
     * after payment) can never contradict a settled order.
     */
    private function applyPaymentEvent(object $event): void
    {
        $handled = ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled'];

        if (! in_array($event->type, $handled, true)) {
            Log::info('Unhandled Stripe event type', ['event' => $event->id, 'type' => $event->type]);

            return;
        }

        $intentId = $event->data->object->id ?? null;
        $order = $intentId
            ? Order::where('stripe_payment_intent_id', $intentId)->lockForUpdate()->first()
            : null;

        if (! $order) {
            Log::warning('Stripe event has no matching order', ['event' => $event->id, 'payment_intent' => $intentId]);

            return;
        }

        $before = ['status' => $order->status, 'payment_status' => $order->payment_status];

        match ($event->type) {
            'payment_intent.succeeded' => $this->markPaid($order),
            'payment_intent.payment_failed' => $this->markFailed($order),
            'payment_intent.canceled' => $this->markCancelled($order),
        };

        Log::info('Stripe event applied', [
            'event' => $event->id,
            'type' => $event->type,
            'order_id' => $order->id,
            'from' => $before,
            'to' => ['status' => $order->status, 'payment_status' => $order->payment_status],
        ]);
    }

    private function markPaid(Order $order): void
    {
        // Don't resurrect an order that was already settled or cancelled (e.g. a
        // stale checkout tab that pays after an admin voided the order).
        if ($order->payment_status === 'paid'
            || $order->status === 'cancelled'
            || $order->payment_status === 'cancelled') {
            return;
        }

        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
    }

    private function markFailed(Order $order): void
    {
        // Never override a settled order; leave status at pending_payment so the
        // customer can retry the same order.
        if ($order->payment_status !== 'pending') {
            return;
        }

        $order->update(['payment_status' => 'failed']);
    }

    private function markCancelled(Order $order): void
    {
        if (! in_array($order->payment_status, ['pending', 'failed'], true)) {
            return;
        }

        $order->update(['payment_status' => 'cancelled', 'status' => 'cancelled']);
    }
}
