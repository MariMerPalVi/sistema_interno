<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataUpdateDocument extends Model
{
    protected $fillable = [
        'data_update_process_id',
        'document_key',
        'display_name',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'manual_signature_confirmed',
        'observations',
        'uploaded_by',
    ];

    protected $casts = [
        'manual_signature_confirmed' => 'boolean',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(DataUpdateProcess::class, 'data_update_process_id');
    }
}
