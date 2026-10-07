<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * KYC (ID / business) documents — a separate, more restrictive path than
 * MediaController: stored on the private `local` disk (storage/app/private),
 * never publicly streamable, and only readable by the owning seller or an
 * admin.
 */
class SellerKycController extends Controller
{
    /**
     * Upload one document ahead of POST /seller/apply. `kind` distinguishes
     * the ID document from the business document; apply() stores whichever
     * paths were returned here.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'kind' => ['required', 'string', 'in:id_document,business_document,license_document,tax_certificate,corporate_document,bank_document,product_document,trademark_certificate'],
        ]);

        $folder = 'kyc/'.$request->user()->id;
        $disk = Storage::disk('local');

        if (! $disk->exists($folder)) {
            $disk->makeDirectory($folder);
        }

        try {
            $path = $request->file('file')->store($folder, 'local');
        } catch (Throwable $e) {
            Log::error('KYC document upload failed', ['kind' => $validated['kind'], 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not save the uploaded document.'], 500);
        }

        if (! $path || ! $disk->exists($path)) {
            Log::error('KYC document upload reported success but file is missing', ['kind' => $validated['kind'], 'path' => $path]);

            return response()->json(['message' => 'The upload did not save correctly.'], 500);
        }

        return response()->json(['data' => ['path' => $path, 'kind' => $validated['kind']]], 201);
    }

    /**
     * Stream a KYC document. Gated: only the seller it belongs to, or an
     * admin (reviewing the application), may read it.
     */
    public function show(Request $request, string $path): BinaryFileResponse|Response
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'kyc/')) {
            abort(404);
        }

        $user = $request->user();
        $ownerId = (int) (explode('/', $path)[1] ?? 0);

        abort_unless($user && ($user->is_admin || $user->id === $ownerId), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path));
    }
}
