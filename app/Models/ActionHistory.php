<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionHistory extends Model
{
    protected $fillable = [
        'account_opening_id',
        'user_id',
        'agency',
        'role',
        'ip_address',
        'user_agent',
        'action',
        'subject_type',
        'subject_id',
        'subject_name',
        'description',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function opening(): BelongsTo
    {
        return $this->belongsTo(AccountOpening::class, 'account_opening_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
