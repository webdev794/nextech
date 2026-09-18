<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    /**
     * Published pages, trimmed for the storefront footer / directory.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Page::query()->published()->ordered()
                ->get(['slug', 'title', 'show_in_footer', 'footer_group']),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [
            'slug' => $page->slug,
            'title' => $page->title,
            'banner_image' => $page->banner_image,
            'content' => (string) $page->content,
            'sections' => is_array($page->sections) ? array_values($page->sections) : [],
            'updated_at' => $page->updated_at,
        ]]);
    }
}
