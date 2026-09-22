<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Seller extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'business_type',
        'company_name',
        'tax_id',
        'registered_line1',
        'registered_line2',
        'registered_city',
        'registered_state',
        'registered_postal_code',
        'registered_country',
        'contact_name',
        'id_type',
        'id_number',
        'date_of_birth',
        'id_document_path',
        'business_document_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
