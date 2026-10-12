<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone applying for a (non-rider) job from the Careers page. */
class JobApplication extends Model
{
    protected $fillable = ['job_role_id', 'user_id', 'name', 'email', 'phone', 'link', 'message', 'status'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(JobRole::class, 'job_role_id');
    }
}
