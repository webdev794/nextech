<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeTile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminHomeTileController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => HomeTile::query()->ordered()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $tile = HomeTile::create($this->validated($request));

        return response()->json(['data' => $tile], 201);
    }

    public function update(Request $request, HomeTile $homeTile): JsonResponse
    {
        $homeTile->update($this->validated($request, partial: true, existing: $homeTile));

        return response()->json(['data' => $homeTile]);
    }

    public function destroy(HomeTile $homeTile): JsonResponse
    {
        $homeTile->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false, ?HomeTile $existing = null): array
    {
        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'category_slug' => ['sometimes', 'nullable', 'string', 'max:255', 'exists:categories,slug'],
            'link_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // A tile needs a destination: a category or a custom link.
        $category = array_key_exists('category_slug', $data) ? $data['category_slug'] : $existing?->category_slug;
        $link = array_key_exists('link_url', $data) ? $data['link_url'] : $existing?->link_url;

        if (! $partial || array_key_exists('category_slug', $data) || array_key_exists('link_url', $data)) {
            if (blank($category) && blank($link)) {
                throw ValidationException::withMessages([
                    'category_slug' => ['Pick a category or set a link URL for this tile.'],
                ]);
            }
        }

        return $data;
    }
}
