<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SpaController extends Controller
{
    /**
     * Serve the built React storefront. Its files are copied into the public
     * path at deploy time; in local dev (no build present) fall back to the
     * framework welcome view. API paths are never handled here.
     */
    public function __invoke(Request $request): Response
    {
        if ($request->is('api/*')) {
            abort(404);
        }

        $index = public_path('index.html');

        return file_exists($index)
            ? response()->file($index)
            : response()->view('welcome');
    }
}
