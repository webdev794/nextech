<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DigitalDownload;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Support\DigitalProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Buyer side of digital products: "Your downloads", and fetching a file
 * through a short-lived signed link (so a plain browser download works
 * without the API token), counted against the download limit.
 */
class DigitalDownloadController extends Controller
{
    /** Everything digital the buyer bought. */
    public function index(Request $request): JsonResponse
    {
        $items = OrderItem::query()
            ->where('fulfilled_by', 'digital')
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id)->whereNotIn('status', ['cancelled']))
            ->latest('id')
            ->get();

        return response()->json(['data' => $items->map(fn (OrderItem $i) => DigitalProducts::present($i))->values()]);
    }

    /** A 10-minute download link for one file. */
    public function link(Request $request, OrderItem $item, ProductFile $file): JsonResponse
    {
        abort_if($reason = DigitalProducts::blockedReason($item, $request->user()->id), 403, (string) $reason);
        abort_unless($file->product_id === $item->product_id, 404);
        $product = Product::findOrFail($item->product_id);
        $limit = DigitalProducts::limitFor($item, $product);
        abort_if($limit > 0 && DigitalProducts::downloadsUsed($item, $file->id) >= $limit, 403, "You've used all {$limit} downloads of this file. Contact support if you need it again.");

        return response()->json(['data' => ['url' => DigitalProducts::signedLink($item, $file)]]);
    }

    /** The signed link itself: stream the private file, or send the buyer to the seller's link. */
    public function download(Request $request, OrderItem $item, ProductFile $file)
    {
        abort_unless($file->product_id === $item->product_id, 404);
        $order = $item->order;
        abort_if($reason = DigitalProducts::blockedReason($item, (int) $order?->user_id), 403, (string) $reason);
        $product = Product::findOrFail($item->product_id);
        $limit = DigitalProducts::limitFor($item, $product);
        abort_if($limit > 0 && DigitalProducts::downloadsUsed($item, $file->id) >= $limit, 403, 'Download limit reached.');

        DigitalDownload::create(['order_item_id' => $item->id, 'product_file_id' => $file->id, 'user_id' => $order->user_id, 'ip' => $request->ip()]);

        if ($file->external_url) {
            return redirect()->away($file->external_url);
        }
        abort_unless($file->path && Storage::disk('local')->exists($file->path), 404, 'The file is missing — contact support.');

        return Storage::disk('local')->download($file->path, $file->original_name ?: basename($file->path));
    }
}
