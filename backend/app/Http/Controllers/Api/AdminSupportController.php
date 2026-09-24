<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\SupportThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'resolved'])],
            // Comma-separated list of issue types (e.g. the Sellers filter chip
            // sends "seller_product_issue,seller_other") — a single value still
            // works exactly as before.
            'issue_type' => ['sometimes', 'string'],
        ]);

        $issueTypes = isset($validated['issue_type'])
            ? array_values(array_intersect(explode(',', $validated['issue_type']), SupportThread::ISSUE_TYPES))
            : [];

        $threads = SupportThread::query()
            ->with(['user:id,name,email', 'order:id,status,total_cents'])
            ->withCount('messages')
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($issueTypes, fn ($q) => $q->whereIn('issue_type', $issueTypes))
            ->with('sellerShop:id,name')
            ->orderByRaw("status = 'open' desc")
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return response()->json([
            'data' => $threads->items(),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'total' => $threads->total(),
                'open' => SupportThread::where('status', 'open')->count(),
            ],
        ]);
    }

    /**
     * Relations the thread drawer needs: the order (for the refund panel) and
     * the customer's own spendable gift cards (so admin can apply an
     * already-issued one to a different open order without the customer
     * needing to type the code themselves).
     */
    private function threadRelations(): array
    {
        return [
            'messages', 'user:id,name,email', 'sellerShop:id,name',
            'user.giftCards' => fn ($q) => $q->where('is_active', true)->where('balance_cents', '>', 0),
            // Recent orders for this customer, so a thread opened without one
            // attached (e.g. from general support) can still be linked to the
            // right order — unlocking the refund / gift-card panel.
            'user.orders' => fn ($q) => $q->select('id', 'user_id', 'status', 'total_cents', 'created_at')->latest()->limit(20),
            'order.items', 'order.refunds', 'order.giftCards', 'order.giftCards.issuedBy:id,name',
        ];
    }

    public function show(SupportThread $thread): JsonResponse
    {
        $thread->load($this->threadRelations());

        return response()->json(['data' => $this->withSellerOptions($thread)]);
    }

    /**
     * Bring a seller into this customer's order chat — a three-way
     * conversation, NexTech stays in it. Only a shop with items on the
     * thread's order qualifies; with just one such shop, it's picked for you.
     * Deliberately one-way: once in, the seller stays part of the conversation
     * (and its record) until it's resolved — nobody can drop them from a
     * dispute that has no easy answer.
     */
    public function addSeller(Request $request, SupportThread $thread): JsonResponse
    {
        abort_if(str_starts_with((string) $thread->issue_type, 'seller_'), 422, 'This is already a seller conversation.');
        abort_unless($thread->order_id, 422, 'Attach an order to this chat first — the seller is picked from that order.');

        $data = $request->validate(['shop_id' => ['sometimes', 'nullable', 'integer']]);
        $options = $this->sellerOptions($thread);
        abort_if($options->isEmpty(), 422, 'No seller items on this order — it was sold by NexTech.');

        $shop = isset($data['shop_id'])
            ? $options->firstWhere('id', (int) $data['shop_id'])
            : ($options->count() === 1 ? $options->first() : null);
        abort_unless($shop, 422, 'Pick which seller to bring in.');

        $thread->forceFill(['seller_shop_id' => $shop->id, 'seller_joined_at' => now()])->save();
        $thread->post(null, "{$shop->name} (the seller) has joined this chat to help with your order.", system: true);

        return response()->json(['data' => $this->withSellerOptions($thread->fresh($this->threadRelations()))]);
    }

    /** Seller shops with items on the thread's order. */
    private function sellerOptions(SupportThread $thread): Collection
    {
        if (! $thread->order_id) {
            return collect();
        }

        $shopIds = OrderItem::query()->where('order_id', $thread->order_id)->whereNotNull('shop_id')->distinct()->pluck('shop_id');

        return Shop::query()->whereIn('id', $shopIds)->get(['id', 'name']);
    }

    private function withSellerOptions(SupportThread $thread): SupportThread
    {
        return $thread->setAttribute('seller_options', $this->sellerOptions($thread));
    }

    public function message(Request $request, SupportThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
            // Photos uploaded first via POST /support/attachments.
            'attachments' => ['sometimes', 'array', 'max:4'],
            'attachments.*' => ['string', 'max:500', 'starts_with:/api/media/file/support/'],
        ]);

        $thread->post($request->user(), trim((string) ($validated['body'] ?? '')), isStaff: true, attachments: $validated['attachments'] ?? []);

        return response()->json(['data' => $this->withSellerOptions($thread->fresh($this->threadRelations()))]);
    }

    public function update(Request $request, SupportThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'resolved'])],
            // Lets the admin attach an order to a thread the customer opened
            // without picking one (e.g. a general "item missing" chat) — must
            // be one of that same customer's own orders.
            'order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('orders', 'id')->where('user_id', $thread->user_id)],
        ]);

        if (! array_key_exists('status', $validated) && ! array_key_exists('order_id', $validated)) {
            return response()->json(['message' => 'Provide a status change or an order to attach.'], 422);
        }

        if (array_key_exists('status', $validated)) {
            $thread->status = $validated['status'];
            $thread->resolved_at = $validated['status'] === 'resolved' ? now() : null;
        }

        if (array_key_exists('order_id', $validated)) {
            $thread->order_id = $validated['order_id'];
        }

        $thread->save();

        return response()->json(['data' => $thread->fresh($this->threadRelations())]);
    }
}
