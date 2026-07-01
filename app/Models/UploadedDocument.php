<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedDocument extends Model
{
    protected $fillable = [
        'account_opening_id',
        'account_type_requirement_id',
        'internal_document_template_id',
        'document_scope',
        'display_name',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'extracted_data',
        'manual_signature_confirmed',
        'uploaded_by',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'manual_signature_confirmed' => 'boolean',
    ];
}
