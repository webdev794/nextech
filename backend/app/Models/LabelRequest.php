<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A seller's request for a NexTech shipping label, fulfilled by an admin uploading it. */
class LabelRequest extends Model
{
    protected $fillable = [
        'order_id', 'shop_id', 'ship_from_address_id', 'items', 'status', 'note', 'admin_note', 'label_path', 'label_template_id',
        'order_package_id', 'handled_by', 'handled_at',
    ];

    protected $hidden = ['label_path'];

    protected $appends = ['has_label_file'];

    public function getHasLabelFileAttribute(): bool
    {
        return $this->label_path !== null;
    }

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'handled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function shipFrom(): BelongsTo
    {
        return $this->belongsTo(ShopAddress::class, 'ship_from_address_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(OrderPackage::class, 'order_package_id');
    }
}
