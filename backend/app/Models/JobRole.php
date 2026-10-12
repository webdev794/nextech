<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A job admin lists on the Careers page (rider roles use the rider application). */
class JobRole extends Model
{
    protected $fillable = ['title', 'kind', 'location', 'store_ids', 'description', 'requirements', 'is_open'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'store_ids' => 'array'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
