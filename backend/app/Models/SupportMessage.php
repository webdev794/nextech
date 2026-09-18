<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = ['support_thread_id', 'user_id', 'is_staff', 'internal', 'body'];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean', 'internal' => 'boolean'];
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
