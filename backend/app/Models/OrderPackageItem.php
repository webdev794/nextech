<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPackageItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_package_id', 'order_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(OrderPackage::class, 'order_package_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
