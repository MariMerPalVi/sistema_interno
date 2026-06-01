<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalDocumentTemplate extends Model
{
    protected $fillable = [
        'account_type_id',
        'name',
        'slug',
        'template_path',
        'file_name_pattern',
        'source',
        'requires_signature',
        'is_required',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'requires_signature' => 'boolean',
        'is_required' => 'boolean',
        'active' => 'boolean',
    ];
}
