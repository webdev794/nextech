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

    /**
     * Categories → edit → Product details: the details sellers fill in for this
     * category, whether it has its own list or uses a parent's (or the built-in
     * one), and which details and choices products use (locked).
     */
    public function details(Category $category): JsonResponse
    {
        return response()->json(['data' => $this->detailsPayload($category)]);
    }

    /** Save this category's own list, or `fields: null` to use its parent's again (only when no product needs it). */
    public function saveDetails(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['present', 'nullable', 'array', 'max:60'],
            'fields.*.key' => ['nullable', 'string', 'max:60'],
            'fields.*.label' => ['nullable', 'string', 'max:80'],
            'fields.*.type' => ['required', 'string', Rule::in(\App\Support\CategoryDetails::TYPES)],
            'fields.*.options' => ['nullable', 'array', 'max:100'],
            'fields.*.options.*' => ['nullable', 'string', 'max:80'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:300'],
        ]);
        $own = is_array($category->detail_fields);
        $usage = \App\Support\CategoryDetails::usage($category);
        if ($data['fields'] === null) {
            abort_if($own && $usage, 422, 'Products use this category’s details — remove the values from those products first, or keep the list.');
            $category->forceFill(['detail_fields' => null])->save();
        } else {
            $current = $this->ownStart($category);
            // Products using the list this replaces keep their values: the same locks apply.
            $category->forceFill(['detail_fields' => \App\Support\CategoryDetails::clean($data['fields'], $current, $usage)])->save();
        }

        return response()->json(['data' => $this->detailsPayload($category->fresh())]);
    }

    /** Categories → Common product details: asked of every physical product, in every category. */
    public function commonDetails(): JsonResponse
    {
        return response()->json(['data' => $this->commonPayload()]);
    }

    public function saveCommonDetails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'max:60'],
            'fields.*.key' => ['nullable', 'string', 'max:60'],
            'fields.*.label' => ['nullable', 'string', 'max:80'],
            'fields.*.type' => ['required', 'string', Rule::in(\App\Support\CategoryDetails::TYPES)],
            'fields.*.options' => ['nullable', 'array', 'max:100'],
            'fields.*.options.*' => ['nullable', 'string', 'max:80'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:300'],
        ]);
        $clean = \App\Support\CategoryDetails::clean($data['fields'], \App\Support\CategoryDetails::common(), \App\Support\CategoryDetails::commonUsage());
        \App\Models\Setting::put('common_detail_fields', $clean);

        return response()->json(['data' => $this->commonPayload()]);
    }

    private function commonPayload(): array
    {
        return ['own' => true, 'common_list' => true, 'fields' => \App\Support\CategoryDetails::common(), 'usage' => \App\Support\CategoryDetails::commonUsage(), 'common' => [], 'core' => \App\Support\CategoryDetails::CORE];
    }

    /** What a category starts from when it gets its own list: the list it uses now (minus the common details). */
    private function ownStart(Category $category): array
    {
        $common = array_column(\App\Support\CategoryDetails::common(), 'key');

        if (is_array($category->detail_fields)) {
            return array_values($category->detail_fields);
        }

        return array_values(array_filter(\App\Support\ProductCatalog::attributesFor($category), fn ($f) => ! in_array($f['key'], $common, true)));
    }

    private function detailsPayload(Category $category): array
    {
        $owner = \App\Support\CategoryDetails::owner($category);
        $own = $owner?->id === $category->id;
        $commonKeys = array_column(\App\Support\CategoryDetails::common(), 'key');
        $list = $own ? array_values((array) $category->detail_fields) : $this->ownStart($category);

        return [
            'own' => $own,
            // Where the list comes from when it isn't this category's own.
            'inherited_from' => $own ? null : ($owner ? $owner->path() : 'Built-in list'),
            'fields' => $list,
            'usage' => \App\Support\CategoryDetails::usage($category),
            // Asked of every physical product, after the category's own (a detail with the same name replaces it).
            'common' => $category->kind === 'digital' ? [] : array_values(array_filter(\App\Support\CategoryDetails::common(), fn ($f) => ! in_array($f['key'], array_column($list, 'key'), true))),
            'common_keys' => $commonKeys,
            'core' => \App\Support\CategoryDetails::CORE,
            'kind' => $category->kind,
        ];
    }
}
