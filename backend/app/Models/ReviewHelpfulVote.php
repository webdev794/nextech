<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One shopper marking a review helpful (once per review). */
class ReviewHelpfulVote extends Model
{
    protected $fillable = ['product_review_id', 'user_id'];
}
