<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Support\Market;
use App\Support\Reviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin -> Reviews: every buyer review waits here until approved — for the
 * product page and the reviewer's public profile, or the product page only
 * — or rejected. Reviews (and their photos) can also be deleted.
 */
class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'all'])],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $status = $data['status'] ?? 'pending';

        $page = ProductReview::query()
            ->whereHas('product', fn ($q) => $q->inMarket(Market::fromRequest($request)))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($data['rating'] ?? null, fn ($q, $r) => $q->where('rating', $r))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('body', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))))
            ->with(['user:id,name,email', 'product' => fn ($q) => $q->with('shop:id,name,is_active')])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ProductReview $r) => Reviews::present($r) + [
                'status' => $r->status,
                'show_on_profile' => $r->show_on_profile,
                'admin_note' => $r->admin_note,
                'reviewer_email' => $r->user?->email,
                'reviewer_full_name' => $r->user?->name,
                'shop' => $r->product?->shop?->name,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'pending' => ProductReview::where('status', 'pending')->whereHas('product', fn ($q) => $q->inMarket(Market::fromRequest($request)))->count(),
        ]);
    }

    public function approve(Request $request, ProductReview $review): JsonResponse
    {
        $data = $request->validate(['show_on_profile' => ['sometimes', 'boolean']]);
        $review->update(['status' => 'approved', 'show_on_profile' => $data['show_on_profile'] ?? true, 'admin_note' => null, 'reviewed_at' => now()]);
        Reviews::refreshProductRating($review->product_id);

        return response()->json(['data' => $review->fresh()]);
    }

    public function reject(Request $request, ProductReview $review): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $review->update(['status' => 'rejected', 'admin_note' => $data['note'] ?? null, 'reviewed_at' => now()]);
        Reviews::refreshProductRating($review->product_id);

        return response()->json(['data' => $review->fresh()]);
    }

    public function destroy(ProductReview $review): JsonResponse
    {
        foreach ((array) $review->images as $url) {
            if (preg_match('#/api/media/file/(reviews/[^/?]+)$#', (string) $url, $m)) {
                Storage::disk('public')->delete($m[1]);
            }
        }
        $productId = $review->product_id;
        $review->delete();
        Reviews::refreshProductRating($productId);

        return response()->json(status: 204);
    }
}
