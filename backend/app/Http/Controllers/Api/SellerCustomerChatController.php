<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\SupportThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer order chats an admin has brought this seller into. The seller
 * sees only the customer's first name and their own items on that order —
 * never the email, phone or address; NexTech stays in the conversation.
 */
class SellerCustomerChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $threads = SupportThread::query()
            ->where('seller_shop_id', $shop->id)
            ->with(['user:id,name', 'order:id,status'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (SupportThread $t) => [
                'id' => $t->id,
                'issue_type' => $t->issue_type,
                'status' => $t->status,
                'order_id' => $t->order_id,
                'order_status' => $t->order?->status,
                'customer_first_name' => self::firstName($t->user?->name),
                'last_message_at' => $t->last_message_at,
                'needs_seller_reply' => self::needsSellerReply($t),
            ]);

        return response()->json(['data' => $threads]);
    }

    public function show(Request $request, SupportThread $thread): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($thread->seller_shop_id === $shop->id, 404);

        return response()->json(['data' => $this->payload($thread, $shop)]);
    }

    public function message(Request $request, SupportThread $thread): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($thread->seller_shop_id === $shop->id, 404);

        $validated = $request->validate([
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
            // Photos uploaded first via POST /support/attachments.
            'attachments' => ['sometimes', 'array', 'max:4'],
            'attachments.*' => ['string', 'max:500', 'starts_with:/api/media/file/support/'],
        ]);

        if ($thread->status === 'resolved') {
            $thread->forceFill(['status' => 'open', 'resolved_at' => null])->save();
        }

        // Counts as a reply to the customer (they get the unread badge), and
        // is labelled as the seller's everywhere it's shown.
        $thread->post($request->user(), trim((string) ($validated['body'] ?? '')), isStaff: true, fromSeller: true, attachments: $validated['attachments'] ?? []);

        return response()->json(['data' => $this->payload($thread->fresh(), $shop)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SupportThread $thread, Shop $shop): array
    {
        $thread->load([
            'user:id,name',
            'order:id,status,created_at',
            'order.items' => fn ($q) => $q->where('shop_id', $shop->id)->select('id', 'order_id', 'product_name', 'quantity', 'line_total_cents'),
            'messages' => fn ($q) => $q->where('internal', false)->where('hidden_from_seller', false),
        ]);

        return [
            'id' => $thread->id,
            'issue_type' => $thread->issue_type,
            'status' => $thread->status,
            'customer_first_name' => self::firstName($thread->user?->name),
            'order' => $thread->order ? [
                'id' => $thread->order->id,
                'status' => $thread->order->status,
                'created_at' => $thread->order->created_at,
                'items' => $thread->order->items,
            ] : null,
            'messages' => $thread->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'attachments' => $m->attachments ?? [],
                'created_at' => $m->created_at,
                // Who sent it, from the seller's point of view.
                'from' => $m->from_seller ? 'you' : ($m->user_id === null ? 'system' : ($m->is_staff ? 'nextech' : 'customer')),
            ])->values(),
        ];
    }

    private static function needsSellerReply(SupportThread $thread): bool
    {
        $last = $thread->messages()->where('internal', false)->where('hidden_from_seller', false)->whereNotNull('user_id')->reorder('id', 'desc')->first();

        // Nothing to answer if the customer's side last spoke by ending or rating the chat.
        $ratedAfter = $thread->rated_at && $last && $thread->rated_at->gte($last->created_at);

        return $last !== null && ! $last->from_seller && ! $last->is_staff && ! $ratedAfter && $thread->status !== 'resolved';
    }

    private static function firstName(?string $name): string
    {
        $first = trim(explode(' ', trim((string) $name))[0] ?? '');

        return $first !== '' ? $first : 'Customer';
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');
        abort_unless($seller->shop, 404, 'No shop found for this seller.');

        return $seller->shop;
    }
}
