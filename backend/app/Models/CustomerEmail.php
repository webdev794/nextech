<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One email sent to a customer — the CRM's email log. */
class CustomerEmail extends Model
{
    protected $fillable = [
        'user_id', 'to_email', 'kind', 'subject', 'body_html', 'order_id', 'campaign_id', 'status', 'error', 'sent_at',
    ];

    protected $hidden = ['body_html'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'campaign_id');
    }
}
