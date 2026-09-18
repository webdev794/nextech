<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function supportThreads(): HasMany
    {
        return $this->hasMany(SupportThread::class);
    }

    /** Gift cards (store credit) issued to this customer. */
    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class);
    }

    /** Orders this user is the delivery rider for. */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_partner_id');
    }

    /** Customer reviews of this user as a delivery rider. */
    public function riderReviews(): HasMany
    {
        return $this->hasMany(RiderReview::class, 'rider_id');
    }

    /** This rider's work sessions (check-in .. check-out). */
    public function riderShifts(): HasMany
    {
        return $this->hasMany(RiderShift::class);
    }

    /** The rider's still-open shift (with its breaks), or null when clocked out. */
    public function currentShift(): ?RiderShift
    {
        return $this->riderShifts()
            ->whereNull('clock_out_at')
            ->with('breaks')
            ->latest('clock_in_at')
            ->first();
    }

    /** True when the rider is clocked in and currently on an unfinished break. */
    public function onBreak(): bool
    {
        return (bool) $this->currentShift()?->breaks->firstWhere('ended_at', null);
    }

    /** Share of delivery offers this rider accepted (0..1), or null if never offered any. */
    public function riderAcceptanceRate(): ?float
    {
        $offers = (int) $this->rider_offers_count;

        if ($offers <= 0) {
            return null;
        }

        $accepted = max(0, $offers - (int) $this->rider_declined_count - (int) $this->rider_missed_count);

        return round($accepted / $offers, 3);
    }

    /** Refresh the denormalised rider rating from the reviews. */
    public function recomputeRiderRating(): void
    {
        $agg = $this->riderReviews()->selectRaw('avg(rating) as a, count(*) as c')->first();
        $this->forceFill([
            'rider_rating_avg' => $agg->c ? round((float) $agg->a, 2) : null,
            'rider_rating_count' => (int) $agg->c,
        ])->save();
    }

    /**
     * Cash this rider is currently holding: collected on a COD order but not
     * yet confirmed handed back to the store. Independent of delivery status.
     */
    public function codHoldingCents(): int
    {
        return (int) $this->deliveries()
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereNull('cash_settled_at')
            ->sum('total_cents');
    }

    /** Earliest still-unsettled COD collection — how long the rider has been holding cash. */
    public function codHoldingSince(): ?Carbon
    {
        $order = $this->deliveries()
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereNull('cash_settled_at')
            ->orderBy('cash_collected_at')
            ->first(['cash_collected_at']);

        return $order?->cash_collected_at;
    }

    /** Admin confirms the rider handed back everything they're currently holding. */
    public function settleCodCash(): int
    {
        $orders = $this->deliveries()
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereNull('cash_settled_at')
            ->get(['id', 'total_cents']);

        $total = (int) $orders->sum('total_cents');

        $this->deliveries()->whereIn('id', $orders->pluck('id'))->update(['cash_settled_at' => now()]);

        return $total;
    }

    /** Stores this rider serves (auto-assignment only considers these). */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'rider_store');
    }

    /**
     * The rider's position for "nearest rider" maths: the live fix when it's
     * fresh (pinged within 15 min), otherwise the admin-set base. Null when we
     * have neither.
     *
     * @return array{lat: float, lng: float, source: 'live'|'base'}|null
     */
    public function riderLocation(): ?array
    {
        $fresh = $this->rider_last_located_at
            && $this->rider_last_located_at->gt(now()->subMinutes(15));

        if ($fresh && $this->rider_last_lat !== null && $this->rider_last_lng !== null) {
            return ['lat' => (float) $this->rider_last_lat, 'lng' => (float) $this->rider_last_lng, 'source' => 'live'];
        }

        if ($this->rider_base_lat !== null && $this->rider_base_lng !== null) {
            return ['lat' => (float) $this->rider_base_lat, 'lng' => (float) $this->rider_base_lng, 'source' => 'base'];
        }

        return null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_rider' => 'boolean',
            'rider_is_active' => 'boolean',
            'rider_rating_avg' => 'float',
            'rider_rating_count' => 'integer',
            'rider_declined_count' => 'integer',
            'rider_missed_count' => 'integer',
            'rider_offers_count' => 'integer',
            'rider_daily_target_minutes' => 'integer',
            'rider_since' => 'datetime',
            'rider_available' => 'boolean',
            'rider_last_seen_at' => 'datetime',
            'rider_base_lat' => 'float',
            'rider_base_lng' => 'float',
            'rider_last_lat' => 'float',
            'rider_last_lng' => 'float',
            'rider_last_located_at' => 'datetime',
        ];
    }
}
