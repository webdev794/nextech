<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportThreadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // One account can be both a customer and a seller: the storefront
        // chat lists only customer threads, Seller Center (?kind=seller) only
        // the seller<->NexTech ones, so neither sees the other's conversations.
        $sellerTypes = self::sellerTypes();
        $threads = $request->user()->supportThreads()
            ->when(
                self::sellerContext($request),
                fn ($q) => $q->whereIn('issue_type', $sellerTypes),
                fn ($q) => $q->whereNotIn('issue_type', $sellerTypes),
            )
            ->with(['order:id,status,total_cents', 'sellerShop:id,name'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json(['data' => $threads]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'issue_type' => ['required', Rule::in(SupportThread::ISSUE_TYPES)],
            'message' => ['required', 'string', 'max:2000'],
            'attachments' => ['sometimes', 'array', 'max:4'],
            'attachments.*' => ['string', 'max:500', 'starts_with:/api/media/file/support/'],
        ]);

        abort_unless(
            in_array($validated['issue_type'], self::sellerTypes(), true) === self::sellerContext($request),
            422,
            'That kind of conversation can only be started from '.(self::sellerContext($request) ? 'the storefront.' : 'Seller Center.')
        );
        abort_if(self::sellerContext($request) && ! $request->user()->seller, 403, 'Seller access required.');

        $orderId = $validated['order_id'] ?? null;
        if ($orderId) {
            $request->user()->orders()->findOrFail($orderId);
        }

        $thread = $request->user()->supportThreads()->create([
            'order_id' => $orderId,
            'issue_type' => $validated['issue_type'],
            'status' => 'open',
        ]);

        $label = str_replace('_', ' ', $validated['issue_type']);
        $thread->post(null, $orderId
            ? "Support request opened about order #{$orderId} — {$label}."
            : "Support request opened — {$label}.", isStaff: false, system: true);
        $thread->post($request->user(), $validated['message'], attachments: $validated['attachments'] ?? []);

        return response()->json(['data' => $this->withMessages($thread)], 201);
    }

    public function show(Request $request, SupportThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 404);
        self::assertContext($request, $thread);

        return response()->json(['data' => $this->withMessages($thread)]);
    }

    public function message(Request $request, SupportThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 404);
        self::assertContext($request, $thread);

        $validated = $request->validate([
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
            // Photos uploaded first via POST /support/attachments.
            'attachments' => ['sometimes', 'array', 'max:4'],
            'attachments.*' => ['string', 'max:500', 'starts_with:/api/media/file/support/'],
        ]);

        if ($thread->status === 'resolved') {
            $thread->forceFill(['status' => 'open', 'resolved_at' => null])->save();
        }

        $thread->post($request->user(), trim((string) ($validated['body'] ?? '')), attachments: $validated['attachments'] ?? []);

        return response()->json(['data' => $this->withMessages($thread)]);
    }

    /**
     * The customer (or, from Seller Center, the seller) wraps up the conversation. Recorded as a system note (not a
     * customer message), so the chat doesn't look like it's waiting on a
     * reply from NexTech or a seller in it.
     */
    public function end(Request $request, SupportThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 404);
        self::assertContext($request, $thread);

        $who = self::sellerContext($request) ? 'Seller' : 'Customer';
        $thread->post(null, "{$who} ended the chat.", isStaff: true, system: true);

        return response()->json(['data' => $this->withMessages($thread)]);
    }

    /**
     * The customer's 1–5 rating (and optional note) for how the conversation
     * went. One rating per thread — a repeat call edits it. Only once staff
     * have replied, so there's something to rate.
     */
    public function rate(Request $request, SupportThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 404);
        self::assertContext($request, $thread);
        // Ratings are customer feedback on support — a seller's own threads
        // with NexTech aren't rated.
        abort_if(str_starts_with((string) $thread->issue_type, 'seller_'), 422, 'Seller conversations cannot be rated.');
        abort_unless($thread->hasStaffReply(), 422, 'There is nothing to rate yet — no reply on this conversation.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $thread->forceFill([
            'rating' => $validated['rating'],
            'rating_comment' => $validated['comment'] ?? null,
            'rated_at' => now(),
        ])->save();

        return response()->json(['data' => $this->withMessages($thread)]);
    }

    /** @return list<string> */
    private static function sellerTypes(): array
    {
        return array_values(array_filter(SupportThread::ISSUE_TYPES, fn ($t) => str_starts_with($t, 'seller_')));
    }

    /**
     * One account can be both a customer and a seller. Seller Center sends
     * ?kind=seller; the storefront doesn't. Seller<->NexTech threads are only
     * reachable from Seller Center and customer threads only from the
     * storefront, so a seller conversation never shows up (or gets unread
     * badges, replies, "end chat" or ratings) in the customer's chat.
     */
    private static function sellerContext(Request $request): bool
    {
        return $request->query('kind') === 'seller' || $request->input('kind') === 'seller';
    }

    private static function assertContext(Request $request, SupportThread $thread): void
    {
        abort_unless(in_array($thread->issue_type, self::sellerTypes(), true) === self::sellerContext($request), 404);
    }

    private function withMessages(SupportThread $thread): SupportThread
    {
        // Internal admin notes (e.g. why a refund was issued) never reach the
        // customer's own view of the conversation.
        return $thread->fresh([
            'messages' => fn ($query) => $query->where('internal', false),
            'order:id,status,total_cents',
            'sellerShop:id,name',
        ]);
    }
}
