<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GiftCard extends Model
{
    protected $fillable = [
        'code', 'pin_hash', 'user_id', 'issued_by', 'support_thread_id', 'order_id',
        'initial_cents', 'balance_cents', 'reason', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'initial_cents' => 'integer',
            'balance_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected $hidden = ['pin_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The admin/staff member who issued this store credit. */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    public function isSpendable(): bool
    {
        return $this->is_active && $this->balance_cents > 0;
    }

    /** A readable, unambiguous code like GC-4K7P-9QW2. */
    public static function makeCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $block = fn () => collect(range(1, 4))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
            $code = 'GC-'.$block().'-'.$block();
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** A short numeric-ish password shared alongside the code. */
    public static function makePin(): string
    {
        return Str::lower(Str::random(8));
    }
}
