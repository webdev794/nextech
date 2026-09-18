<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderShift extends Model
{
    protected $fillable = ['user_id', 'clock_in_at', 'clock_out_at', 'source'];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(RiderShiftBreak::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('clock_out_at');
    }

    /** Minutes spent on breaks — open breaks are counted up to now. */
    public function breakMinutes(): int
    {
        return (int) $this->breaks->sum(
            fn (RiderShiftBreak $b) => $b->started_at->diffInMinutes($b->ended_at ?? now())
        );
    }

    /** Net worked minutes — gross shift length minus breaks; open shift counts to now. */
    public function workedMinutes(): int
    {
        $gross = $this->clock_in_at->diffInMinutes($this->clock_out_at ?? now());

        return max(0, (int) $gross - $this->breakMinutes());
    }
}
