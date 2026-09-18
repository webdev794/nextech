<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// The React storefront is served from public/index.html; the API lives under
// /api/* (routes/api.php). Using a controller keeps `route:cache` working.
Route::get('/', SpaController::class);

/**
 * Legacy /storage/{path} → storage/app/public/{path}.
 * Prefer /api/media/file/... on this host; keep this for old DB URLs when PHP is hit.
 */
Route::get('/storage/{path}', function (string $path) {
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');

    if ($path === '' || str_contains($path, '..')) {
        abort(404);
    }

    $relative = preg_replace('#^(app/public/)+#', '', $path);
    $root = storage_path('app/public');
    $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

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
})->where('path', '.*');

Route::fallback(SpaController::class);
