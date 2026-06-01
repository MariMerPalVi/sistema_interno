<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTypeRequirement extends Model
{
    protected $fillable = ['account_type_id', 'requirement_type_id', 'label', 'file_name_pattern', 'is_required', 'active', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(RequirementType::class, 'requirement_type_id');
    }
}
