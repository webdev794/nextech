<?php

namespace App\Models;

use App\Support\PublicMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeTile extends Model
{
    protected $fillable = [
        'title', 'image_url', 'category_slug', 'link_url', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return PublicMedia::url($value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_slug', 'slug');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
