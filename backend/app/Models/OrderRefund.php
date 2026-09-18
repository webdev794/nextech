<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefund extends Model
{
    protected $fillable = [
        'order_id', 'support_thread_id', 'created_by', 'amount_cents', 'reason', 'stripe_refund_id',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function thread(): BelongsTo { return $this->belongsTo(SupportThread::class, 'support_thread_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
