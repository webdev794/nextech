<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'label', 'name', 'line1', 'line2', 'city', 'state', 'postal_code', 'latitude', 'longitude', 'is_default'];

    protected function casts(): array { return ['is_default' => 'boolean', 'latitude' => 'float', 'longitude' => 'float']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}