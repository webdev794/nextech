<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFeedback extends Model
{
    protected $table = 'site_feedback';

    protected $fillable = ['user_id', 'rating'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
