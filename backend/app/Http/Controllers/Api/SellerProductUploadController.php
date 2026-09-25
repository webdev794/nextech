<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductUploadTask;
use App\Models\Shop;
use App\Support\ProductUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Products -> Add products via upload: the uploaded spreadsheet (kept so it
 * can be downloaded with the results), the rows the browser read from it,
 * and a task per upload that ends as Completed (every product submitted) or
 * Action required (some saved as drafts to fix).
 */
class SellerProductUploadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ProductUploadTask::where('shop_id', $this->shop($request)->id)->latest('id')->limit(50)->get()]);
    }

    public function show(Request $request, ProductUploadTask $task): JsonResponse
    {
        abort_unless($task->shop_id === $this->shop($request)->id, 404);

        return response()->json(['data' => $task->makeVisible('rows')]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx'],
            'rows' => ['required', 'json'],
            'category_ids' => ['required', 'json'],
        ]);
        $rows = json_decode($data['rows'], true);
        $categoryIds = json_decode($data['category_ids'], true);
        abort_unless(is_array($rows) && is_array($categoryIds), 422, 'The file couldn’t be read — download a new template and try again.');
        abort_if(count($rows) > ProductUpload::MAX_ROWS, 422, 'Upload at most '.ProductUpload::MAX_ROWS.' rows at a time.');
        abort_if(count($categoryIds) > ProductUpload::MAX_CATEGORIES, 422, 'Templates can cover at most '.ProductUpload::MAX_CATEGORIES.' categories.');

        $path = $request->file('file')->store('product-uploads/'.$shop->id, 'local');
        $task = ProductUploadTask::create([
            'shop_id' => $shop->id,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'status' => 'processing',
            'records' => count($rows),
            'rows' => $rows,
        ]);

        $outcome = ProductUpload::process($shop, $rows, array_map('intval', $categoryIds));
        $task->update([
            'status' => $outcome['error_records'] ? 'action_required' : 'completed',
            'error_records' => $outcome['error_records'],
            'records' => count($outcome['results']),
            'results' => $outcome['results'],
        ]);

        return response()->json(['data' => $task->fresh()->makeVisible('rows')], 201);
    }

    /** The spreadsheet as uploaded. */
    public function file(Request $request, ProductUploadTask $task): BinaryFileResponse
    {
        abort_unless($task->shop_id === $this->shop($request)->id && $task->file_path && Storage::disk('local')->exists($task->file_path), 404);

        return response()->download(Storage::disk('local')->path($task->file_path), $task->file_name);
    }

    private function shop(Request $request): Shop
    {
        $seller = $request->user()->seller;
        abort_unless($seller?->status === 'approved' && $seller->shop, 403, 'Approved seller access required.');

        return $seller->shop;
    }
}
