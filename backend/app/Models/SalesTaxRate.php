<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A US ZIP code's combined sales-tax rate from the live lookup, cached (see App\Support\SalesTax). */
class SalesTaxRate extends Model
{
    protected $primaryKey = 'zip_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['zip_code', 'rate_bps', 'source', 'fetched_at'];

    protected function casts(): array
    {
        return ['rate_bps' => 'integer', 'fetched_at' => 'datetime'];
    }
}
