<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // In tree order, each with its path ("Downloadable › Games") and depth for indenting.
        $categories = Category::query()->withCount(['products', 'children'])->get()
            ->map(fn (Category $c) => $c->setAttribute('path', $c->path())->setAttribute('depth', $c->depth())
                ->setAttribute('tree_key', implode('/', array_map(fn ($a) => sprintf('%05d', $a->sort_order).$a->name, $c->lineage()))))
            ->sortBy('tree_key')->values();

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] ??= $this->uniqueSlug($data['name']);

        return response()->json(['data' => Category::create($data)], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $category->update($this->validated($request, $category));

        return response()->json(['data' => $category->fresh()->loadCount('products')]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists()) {
            return response()->json(['message' => 'Move or delete its subcategories first.'], 409);
        }
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Move or remove this category\'s products before deleting it.',
            ], 409);
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'This category is still in use.'], 409);
        }

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('categories')->ignore($category?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'show_on_home' => ['sometimes', 'boolean'],
            // Tree: a parent (up to 3 levels deep), and Physical / Digital for a top-level category.
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'kind' => ['sometimes', Rule::in(Category::KINDS)],
        ]);
        if (! empty($data['parent_id'])) {
            abort_if($category && in_array((int) $data['parent_id'], Category::withDescendantIds($category->id), true), 422, 'A category can’t go under itself or one of its own subcategories.');
            $parent = Category::find($data['parent_id']);
            $below = $category ? max(array_map(fn ($id) => Category::tree()->get($id)?->depth() ?? 0, Category::withDescendantIds($category->id))) - $category->depth() : 0;
            abort_if($parent->depth() + 1 + $below > 2, 422, 'Categories go at most 3 levels deep (e.g. Downloadable › Games › Arcade).');
        }

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
