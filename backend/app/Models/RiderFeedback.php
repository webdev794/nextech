<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A rider's private rating of the store and the buyer for one delivery (admin only). */
class RiderFeedback extends Model
{
    protected $table = 'rider_feedback';

    protected $fillable = ['rider_id', 'order_id', 'shop_id', 'store_rating', 'buyer_rating', 'note'];
}
