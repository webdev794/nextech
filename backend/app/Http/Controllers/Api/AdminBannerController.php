<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBannerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Banner::query()->ordered()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $banner = Banner::create($this->validated($request));

        return response()->json(['data' => $banner], 201);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $banner->update($this->validated($request, partial: true));

        return response()->json(['data' => $banner]);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'image_url' => [$required, 'string', 'max:2048'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category_slug' => ['sometimes', 'nullable', 'string', 'max:255', 'exists:categories,slug'],
            'link_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'placement' => ['sometimes', 'in:hero,strip'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
