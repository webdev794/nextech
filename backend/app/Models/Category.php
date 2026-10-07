<?php

namespace App\Models;

use App\Support\PublicMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'is_active',
        'sort_order',
        'show_on_home',
        'parent_id',
        'kind',
    ];

    public const KINDS = ['physical', 'digital'];

    /** @var \Illuminate\Support\Collection<int, self>|null every category, for walking the tree (per request) */
    private static $tree = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'show_on_home' => 'boolean',
            'detail_fields' => 'array',
        ];
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    protected static function booted(): void
    {
        // A subcategory is always the same kind (physical / digital) as its parent.
        static::saving(function (Category $category): void {
            if ($category->parent_id && ($parent = self::find($category->parent_id))) {
                $category->kind = $parent->kind;
            }
            $category->kind = in_array($category->kind, self::KINDS, true) ? $category->kind : 'physical';
        });
        // Changing a top-level kind carries down to its subcategories.
        static::saved(function (Category $category): void {
            self::$tree = null;
            if ($category->wasChanged('kind')) {
                foreach ($category->children()->get() as $child) {
                    if ($child->kind !== $category->kind) {
                        $child->update(['kind' => $category->kind]);
                    }
                }
            }
        });
        static::deleted(fn () => self::$tree = null);
    }

    /** Every category, keyed by id (cached for the request). */
    public static function tree(): \Illuminate\Support\Collection
    {
        return self::$tree ??= self::query()->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
    }

    /** This category and every category under it (ids). */
    public static function withDescendantIds(int $id): array
    {
        $tree = self::tree();
        $ids = [$id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($tree as $c) {
                if ($c->parent_id === $ids[$i] && ! in_array($c->id, $ids, true)) {
                    $ids[] = $c->id;
                }
            }
        }

        return $ids;
    }

    /** @return list<self> from the top-level category down to this one */
    public function lineage(): array
    {
        $tree = self::tree();
        $chain = [];
        for ($c = $this, $guard = 0; $c && $guard < 10; $c = $c->parent_id ? $tree->get($c->parent_id) : null, $guard++) {
            array_unshift($chain, $c);
        }

        return $chain;
    }

    /** "Downloadable › Games › Arcade". */
    public function path(): string
    {
        return implode(' › ', array_map(fn ($c) => $c->name, $this->lineage()));
    }

    public function depth(): int
    {
        return count($this->lineage()) - 1;
    }
}