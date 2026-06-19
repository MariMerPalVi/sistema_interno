<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataUpdateHistory extends Model
{
    protected $fillable = [
        'data_update_process_id',
        'user_id',
        'action',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(DataUpdateProcess::class, 'data_update_process_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
