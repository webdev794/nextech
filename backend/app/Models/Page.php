<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title', 'banner_image', 'content', 'sections', 'is_published', 'show_in_footer', 'footer_group', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('footer_group')->orderBy('sort_order')->orderBy('title');
    }
}
