<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReviewHelpfulVote;
use App\Models\User;
use App\Support\Market;
use App\Support\Reviews;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Buyer reviews: writing one for a received item (with photos), reading a
 * product's reviews, a reviewer's public profile (Temu-style: every review
 * they wrote, filterable by stars), and marking reviews helpful. Only
 * reviews admin approved are ever shown to other people.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 10;

    public function forProduct(Request $request, Product $product): JsonResponse
    {
        $base = ProductReview::where('product_id', $product->id)->where('status', 'approved');

        return response()->json($this->listing($request, $base) + ['summary' => Reviews::summary(clone $base)]);
    }

    /** A reviewer's public profile: approved reviews they allowed on it. */
    public function forReviewer(Request $request, User $user): JsonResponse
    {
        $base = ProductReview::where('user_id', $user->id)->where('status', 'approved')->where('show_on_profile', true);
        $summary = Reviews::summary(clone $base);

        return response()->json($this->listing($request, $base) + [
            'profile' => [
                'id' => $user->id,
                'name' => Reviews::publicName($user->name),
                'reviews' => $summary['count'],
                'helpfuls' => (int) (clone $base)->sum('helpful_count'),
                'by_star' => $summary['by_star'],
            ],
        ]);
    }

    /** Write a review for an item the buyer received. One per order item; it waits for approval. */
    public function store(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        $user = $request->user();
        abort_unless($order->user_id === $user->id && $item->order_id === $order->id, 404);
        abort_unless(Reviews::received($order, $item), 422, 'You can review an item once it has been delivered.');
        abort_if(ProductReview::where('order_item_id', $item->id)->exists(), 422, 'You already reviewed this item.');
        abort_unless($item->product_id, 422, 'This product is no longer available to review.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'fit' => ['nullable', Rule::in(['small', 'true_to_size', 'large'])],
            'images' => ['sometimes', 'array', 'max:6'],
            'images.*' => ['string', 'max:500', 'starts_with:/api/media/file/reviews/'],
        ]);

        $review = ProductReview::create([
            'user_id' => $user->id,
            'product_id' => $item->product_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'variant_label' => $item->variant_label,
            'rating' => $data['rating'],
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            'fit' => $data['fit'] ?? null,
            'images' => array_values($data['images'] ?? []),
            'status' => 'pending',
        ]);

        return response()->json(['data' => $review], 201);
    }

    /** Toggle "Helpful" on someone else's approved review. */
    public function helpful(Request $request, ProductReview $review): JsonResponse
    {
        $user = $request->user();
        abort_unless($review->status === 'approved', 404);
        abort_if($review->user_id === $user->id, 422, 'You can’t mark your own review helpful.');

        $voted = DB::transaction(function () use ($review, $user) {
            $existing = ReviewHelpfulVote::where('product_review_id', $review->id)->where('user_id', $user->id)->first();
            if ($existing) {
                $existing->delete();
            } else {
                ReviewHelpfulVote::create(['product_review_id' => $review->id, 'user_id' => $user->id]);
            }
            $review->update(['helpful_count' => $review->votes()->count()]);

            return ! $existing;
        });

        return response()->json(['data' => ['helpful_count' => $review->fresh()->helpful_count, 'voted' => $voted]]);
    }

    /**
     * @param  Builder<ProductReview>  $base
     * @return array<string, mixed>
     */
    private function listing(Request $request, Builder $base): array
    {
        $data = $request->validate([
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'photos' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $viewer = auth('sanctum')->user();

        $page = (clone $base)
            ->when($data['rating'] ?? null, fn ($q, $r) => $q->where('rating', $r))
            ->when($data['photos'] ?? false, fn ($q) => $q->whereNotNull('images')->where('images', '!=', '[]'))
            ->with(['user:id,name', 'product' => fn ($q) => $q->with('shop:id,is_active')])
            ->latest()
            ->paginate(self::PER_PAGE);

        $voted = $viewer ? ReviewHelpfulVote::where('user_id', $viewer->id)->whereIn('product_review_id', $page->getCollection()->pluck('id'))->pluck('product_review_id')->all() : [];

        return [
            'data' => $page->getCollection()->map(fn (ProductReview $r) => Reviews::present($r) + ['voted' => in_array($r->id, $voted, true)])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ];
    }
}
