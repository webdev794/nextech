<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An admin email: to one customer or an audience, sent now or on a schedule (optionally repeating). */
class EmailCampaign extends Model
{
    public const REPEATS = ['none', 'daily', 'weekly', 'monthly'];

    protected $fillable = [
        'name', 'subject', 'body', 'audience', 'promotional', 'send_at', 'repeat', 'status', 'last_run_at', 'sent_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'promotional' => 'boolean',
            'send_at' => 'datetime',
            'last_run_at' => 'datetime',
            'sent_count' => 'integer',
        ];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CustomerEmail::class, 'campaign_id');
    }
}
