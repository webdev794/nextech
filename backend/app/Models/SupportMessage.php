<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = ['support_thread_id', 'user_id', 'is_staff', 'from_seller', 'internal', 'hidden_from_seller', 'body', 'attachments'];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean', 'from_seller' => 'boolean', 'internal' => 'boolean', 'hidden_from_seller' => 'boolean', 'attachments' => 'array'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class, 'support_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
