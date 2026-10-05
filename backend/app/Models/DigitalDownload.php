<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One download of a purchased digital file (counts against the download limit). */
class DigitalDownload extends Model
{
    protected $fillable = ['order_item_id', 'product_file_id', 'user_id', 'ip'];
}
