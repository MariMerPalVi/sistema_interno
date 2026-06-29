<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalDataConsent extends Model
{
    protected $fillable = [
        'account_opening_id',
        'template_path',
        'signed_file_path',
        'status',
        'auto_signature_detected',
        'manual_signature_confirmed',
        'validated_by',
        'validated_at',
        'observations',
    ];

    protected $casts = [
        'auto_signature_detected' => 'boolean',
        'manual_signature_confirmed' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function opening(): BelongsTo
    {
        return $this->belongsTo(AccountOpening::class, 'account_opening_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
