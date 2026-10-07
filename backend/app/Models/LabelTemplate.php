<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An admin-designed shipping label layout; labels are generated from it per request. */
class LabelTemplate extends Model
{
    public const SIZES = ['4x6', 'a6', 'a4'];

    protected $fillable = [
        'name', 'size', 'header_text', 'logo_url', 'footer_note', 'show_items', 'show_phone', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'show_items' => 'boolean',
            'show_phone' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
