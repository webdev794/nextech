<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingTemplateGroup extends Model
{
    protected $fillable = ['shipping_template_id', 'regions', 'transit_min_days', 'transit_max_days', 'fee_cents', 'sort_order'];

    protected function casts(): array
    {
        return [
            'regions' => 'array',
            'transit_min_days' => 'integer',
            'transit_max_days' => 'integer',
            'fee_cents' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShippingTemplate::class, 'shipping_template_id');
    }
}
