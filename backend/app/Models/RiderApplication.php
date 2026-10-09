<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderApplication extends Model
{
    protected $hidden = ['payout_details'];

    protected $fillable = [
        'user_id', 'store_id', 'phone', 'email', 'date_of_birth', 'home_address', 'home_lat', 'home_lng', 'vehicle_type', 'own_vehicle', 'experience_months', 'education', 'work_history', 'health_issue', 'health_details', 'preferred_store_ids', 'consent_removal', 'rc_document_path',
        'license_number', 'license_document_path', 'id_document_path', 'education_document_path', 'photo_path',
        'status', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'decided_by_seller', 'signed_name', 'signed_place', 'signed_at', 'signed_terms', 'payout_method', 'payout_details',
    ];

    protected function casts(): array
    {
        return [
            'signed_terms' => 'array',
            'payout_details' => 'encrypted:array',
            'signed_at' => 'datetime',
            'home_lat' => 'float',
            'home_lng' => 'float',
            'reviewed_at' => 'datetime',
            'date_of_birth' => 'date',
            'own_vehicle' => 'boolean',
            'decided_by_seller' => 'boolean',
            'health_issue' => 'boolean',
            'consent_removal' => 'boolean',
            'preferred_store_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
