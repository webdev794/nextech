<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductLicenseKey;
use App\Models\Shop;
use App\Support\DigitalProducts;
use App\Support\DownloadLinkCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seller side of digital products: upload the download files (in 5 MB
 * chunks, so big games and installers get past the server's upload limit),
 * or add a link the seller hosts; rename / remove files; and manage license
 * keys. Files are private — buyers only reach them through signed links.
 */
class SellerDigitalController extends Controller
{
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->own($request, $product);

        return response()->json(['data' => $this->payload($product)]);
    }

    /**
     * One chunk of a file. The last chunk assembles the file and adds it to
     * the product. Chunks: upload_id (client-made), index (0-based), total.
     */
    public function chunk(Request $request, Product $product): JsonResponse
    {
        $this->own($request, $product);
        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:'.(int) ceil(DigitalProducts::MAX_FILE_BYTES / (256 * 1024))],
            'name' => ['required', 'string', 'max:255'],
            'chunk' => ['required', 'file', 'max:'.(int) (DigitalProducts::MAX_CHUNK_BYTES / 1024 + 64)],
        ]);
        abort_if($data['index'] >= $data['total'], 422, 'Bad chunk.');
        $dir = "digital-chunks/{$product->id}/{$data['upload_id']}";
        // Stop an over-limit upload as soon as it's over, not after it's all on disk.
        $received = collect(Storage::disk('local')->files($dir))->sum(fn ($part) => Storage::disk('local')->size($part)) + (int) $request->file('chunk')->getSize();
        $used = (int) $product->files()->whereNull('external_url')->sum('size_bytes');
        if ($received > DigitalProducts::maxFileBytes() || $used + $received > DigitalProducts::maxProductBytes()) {
            Storage::disk('local')->deleteDirectory($dir);
            abort(422, $received > DigitalProducts::maxFileBytes()
                ? 'Files can be up to '.$this->mb(DigitalProducts::maxFileBytes()).' here — for a bigger file, add a download link to where you host it.'
                : 'This product\'s uploads can total '.$this->mb(DigitalProducts::maxProductBytes()).' — remove a file, or add a download link instead.');
        }
        Storage::disk('local')->putFileAs($dir, $request->file('chunk'), str_pad((string) $data['index'], 6, '0', STR_PAD_LEFT));

        $have = count(Storage::disk('local')->files($dir));
        if ($have < $data['total']) {
            return response()->json(['data' => ['received' => $have, 'total' => $data['total']]]);
        }

        // All parts here: join them into the final private file.
        $safe = Str::limit(preg_replace('/[^A-Za-z0-9._-]+/', '-', $data['name']), 120, '');
        $target = "digital/{$product->id}/".Str::random(16).'-'.$safe;
        $disk = Storage::disk('local');
        $disk->makeDirectory("digital/{$product->id}");
        $out = fopen($disk->path($target), 'wb');
        $size = 0;
        foreach (collect($disk->files($dir))->sort()->values() as $part) {
            $in = fopen($disk->path($part), 'rb');
            $size += stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        $disk->deleteDirectory($dir);
        if ($size > DigitalProducts::maxFileBytes()) {
            $disk->delete($target);
            abort(422, 'Files can be up to '.$this->mb(DigitalProducts::maxFileBytes()).'.');
        }

        $file = $product->files()->create([
            'name' => pathinfo($data['name'], PATHINFO_FILENAME) ?: 'Download',
            'original_name' => $data['name'],
            'path' => $target,
            'size_bytes' => $size,
            'sort_order' => (int) $product->files()->max('sort_order') + 1,
        ]);

        return response()->json(['data' => $this->payload($product), 'file_id' => $file->id], 201);
    }

    /** A download link the seller hosts (e.g. their own server or cloud storage), for very large files. */
    public function storeLink(Request $request, Product $product): JsonResponse
    {
        $this->own($request, $product);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'external_url' => ['required', 'url:https', 'max:1000'],
        ]);
        // Share links become direct downloads; a link that's broken or asks to sign in is refused.
        $data['external_url'] = DownloadLinkCheck::normalize($data['external_url']);
        $check = DownloadLinkCheck::check($data['external_url']);
        abort_if($check['status'] === 'broken', 422, (string) $check['note']);
        $product->files()->create($data + [
            'link_status' => $check['status'], 'link_note' => $check['note'], 'link_checked_at' => now(),
            'size_bytes' => $check['size'],
            'sort_order' => (int) $product->files()->max('sort_order') + 1,
        ]);

        return response()->json(['data' => $this->payload($product), 'check' => $check], 201);
    }

    /** Re-check a hosted link (it may have been moved or unshared since). */
    public function checkLink(Request $request, Product $product, ProductFile $file): JsonResponse
    {
        $this->own($request, $product);
        abort_unless($file->product_id === $product->id && $file->external_url, 404);
        DigitalProducts::recheckLink($file);

        return response()->json(['data' => $this->payload($product)]);
    }

    public function updateFile(Request $request, Product $product, ProductFile $file): JsonResponse
    {
        $this->own($request, $product);
        abort_unless($file->product_id === $product->id, 404);
        $file->update($request->validate(['name' => ['required', 'string', 'max:160']]));

        return response()->json(['data' => $this->payload($product)]);
    }

    public function destroyFile(Request $request, Product $product, ProductFile $file): JsonResponse
    {
        $this->own($request, $product);
        abort_unless($file->product_id === $product->id, 404);
        if ($file->path) {
            Storage::disk('local')->delete($file->path);
        }
        $file->delete();

        return response()->json(['data' => $this->payload($product)]);
    }

    /** Add license keys (one per line). */
    public function addKeys(Request $request, Product $product): JsonResponse
    {
        $this->own($request, $product);
        $data = $request->validate(['keys' => ['required', 'string', 'max:200000']]);
        $result = DigitalProducts::addKeys($product, $data['keys']);

        return response()->json(['data' => $this->payload($product), 'result' => $result]);
    }

    /** Remove the keys not yet given to buyers. */
    public function clearKeys(Request $request, Product $product): JsonResponse
    {
        $this->own($request, $product);
        ProductLicenseKey::where('product_id', $product->id)->whereNull('order_item_id')->delete();
        DigitalProducts::syncStock($product);

        return response()->json(['data' => $this->payload($product)]);
    }

    private function payload(Product $product): array
    {
        $product->unsetRelation('files');

        return [
            'files' => $product->files()->get()->map(fn (ProductFile $f) => [
                'id' => $f->id, 'name' => $f->name, 'original_name' => $f->original_name, 'size_bytes' => $f->size_bytes,
                'external_url' => $f->external_url, 'link_status' => $f->link_status, 'link_note' => $f->link_note, 'link_checked_at' => $f->link_checked_at, 'created_at' => $f->created_at,
            ])->values(),
            'keys_available' => $product->licenseKeys()->whereNull('order_item_id')->count(),
            'keys_assigned' => $product->licenseKeys()->whereNotNull('order_item_id')->count(),
            'chunk_bytes' => DigitalProducts::chunkBytes(),
            'max_file_bytes' => DigitalProducts::maxFileBytes(),
            'max_product_bytes' => DigitalProducts::maxProductBytes(),
            'used_bytes' => (int) $product->files()->whereNull('external_url')->sum('size_bytes'),
        ];
    }

    private function own(Request $request, Product $product): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');
        abort_unless($product->shop_id === $seller->shop->id, 404);

        return $seller->shop;
    }

    private function mb(int $bytes): string
    {
        return $bytes >= 1024 * 1024 * 1024 ? round($bytes / 1024 ** 3, 1).' GB' : round($bytes / 1024 ** 2).' MB';
    }
}
