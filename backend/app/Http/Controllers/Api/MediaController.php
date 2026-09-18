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

class MediaController extends Controller
{
    /**
     * Store on storage/app/public/{folder} (unchanged layout).
     *
     * Returns an /api/media/file/... URL so the browser always hits Laravel.
     * On this host, /storage/... does not reach the files (nginx static 404 /
     * blocked path). /api/* already works (upload itself proved that).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'folder' => ['sometimes', 'string', 'in:products,categories,stores,banners'],
        ]);

        $folder = $validated['folder'] ?? 'products';
        $disk = Storage::disk('public');

        if (! $disk->exists($folder)) {
            $disk->makeDirectory($folder);
        }

        try {
            $path = $request->file('file')->store($folder, 'public');
        } catch (Throwable $e) {
            Log::error('Media upload failed', ['folder' => $folder, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not save the uploaded file. Check that storage/app/public/'.$folder.' exists and is writable.',
            ], 500);
        }

        if (! $path || ! $disk->exists($path)) {
            Log::error('Media upload reported success but file is missing', ['folder' => $folder, 'path' => $path]);

            return response()->json([
                'message' => 'The upload did not save correctly. Check storage/app/public/'.$folder.' is writable on the server.',
            ], 500);
        }

        return response()->json([
            'data' => [
                // Root-relative API path — frontend prefixes app base (/gdp/nextech_demo).
                'url' => '/api/media/file/'.$path,
                'path' => $path,
            ],
        ], 201);
    }

    /**
     * Stream a file from storage/app/public. Public, no auth — same visibility
     * as a normal /storage link. Path is constrained under the public disk root.
     */
    public function show(string $path): BinaryFileResponse|Response
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $root = storage_path('app/public');
        $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

        $file = realpath($candidate);
        $rootReal = realpath($root);

        if ($file !== false && $rootReal !== false) {
            $fileNorm = str_replace('\\', '/', $file);
            $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
            if (! str_starts_with($fileNorm, $rootNorm.'/')) {
                abort(404);
            }
        } else {
            $file = $candidate;
        }

        abort_unless(is_file($file) && is_readable($file), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
