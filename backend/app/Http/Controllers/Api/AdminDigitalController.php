<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DigitalDownload;
use App\Models\Product;
use App\Models\ProductFile;
use App\Support\DigitalProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Admin → Products → Files: everything a digital product delivers — uploaded
 * files (downloadable here to inspect), the seller's hosted links with their
 * last check, license-key stock and the download settings.
 */
class AdminDigitalController extends Controller
{
    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $this->payload($product)]);
    }

    public function checkLink(Product $product, ProductFile $file): JsonResponse
    {
        abort_unless($file->product_id === $product->id && $file->external_url, 404);
        DigitalProducts::recheckLink($file);

        return response()->json(['data' => $this->payload($product)]);
    }

    public function download(Product $product, ProductFile $file)
    {
        abort_unless($file->product_id === $product->id, 404);
        if ($file->external_url) {
            return redirect()->away($file->external_url);
        }
        abort_unless($file->path && Storage::disk('local')->exists($file->path), 404, 'The file is missing.');

        return Storage::disk('local')->download($file->path, $file->original_name ?: basename($file->path));
    }

    private function payload(Product $product): array
    {
        $downloads = DigitalDownload::whereIn('product_file_id', $product->files()->pluck('id'))
            ->selectRaw('product_file_id, count(*) as n')->groupBy('product_file_id')->pluck('n', 'product_file_id');

        return [
            'product' => ['id' => $product->id, 'name' => $product->name, 'product_type' => $product->product_type],
            'settings' => DigitalProducts::settings($product),
            'files' => $product->files()->get()->map(fn (ProductFile $f) => [
                'id' => $f->id, 'name' => $f->name, 'original_name' => $f->original_name, 'size_bytes' => $f->size_bytes,
                'external_url' => $f->external_url, 'link_status' => $f->link_status, 'link_note' => $f->link_note,
                'link_checked_at' => $f->link_checked_at, 'downloads' => (int) ($downloads[$f->id] ?? 0), 'created_at' => $f->created_at,
            ])->values(),
            'keys_available' => $product->licenseKeys()->whereNull('order_item_id')->count(),
            'keys_assigned' => $product->licenseKeys()->whereNotNull('order_item_id')->count(),
        ];
    }
}
