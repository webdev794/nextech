<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A self-shipping seller's rates: which address it ships from, how many
 * working days to hand over to the carrier, and region groups (states) each
 * with a transit time and fee.
 */
class ShippingTemplate extends Model
{
    protected $fillable = ['shop_id', 'name', 'product_type', 'shop_address_id', 'handling_days', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'handling_days' => 'integer'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ShopAddress::class, 'shop_address_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ShippingTemplateGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The group covering a state: an explicit match first, then the "ALL" catch-all. */
    public function groupFor(?string $stateCode): ?ShippingTemplateGroup
    {
        $groups = $this->relationLoaded('groups') ? $this->groups : $this->groups()->get();

        return $groups->first(fn ($g) => $stateCode && in_array($stateCode, (array) $g->regions, true))
            ?? $groups->first(fn ($g) => in_array('ALL', (array) $g->regions, true));
    }
}
