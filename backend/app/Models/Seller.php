<?php

namespace App\Models;

use App\Support\Market;
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
        'pickup_same_as_registered',
        'pickup_phone',
        'pickup_line1',
        'pickup_line2',
        'pickup_city',
        'pickup_state',
        'pickup_postal_code',
        'pickup_country',
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
        'payout_method',
        'payout_details',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'payout_details' => 'array',
            'pickup_same_as_registered' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        // Changing the seller's country moves their shop (and products) to that market.
        static::updated(function (Seller $seller): void {
            if ($seller->wasChanged('country') && $seller->shop) {
                $seller->shop->update(['market' => Market::forCountry($seller->country)]);
            }
        });
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Where a courier should collect this seller's orders from, resolving the same-as-registered flag. */
    public function pickupAddress(): array
    {
        $sameAsRegistered = (bool) $this->pickup_same_as_registered;

        return [
            'phone' => $this->pickup_phone,
            'line1' => $sameAsRegistered ? $this->registered_line1 : $this->pickup_line1,
            'line2' => $sameAsRegistered ? $this->registered_line2 : $this->pickup_line2,
            'city' => $sameAsRegistered ? $this->registered_city : $this->pickup_city,
            'state' => $sameAsRegistered ? $this->registered_state : $this->pickup_state,
            'postal_code' => $sameAsRegistered ? $this->registered_postal_code : $this->pickup_postal_code,
            'country' => $sameAsRegistered ? $this->registered_country : $this->pickup_country,
        ];
    }
}
