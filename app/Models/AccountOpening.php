<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountOpening extends Model
{
    protected $fillable = [
        'public_code',
        'file_name',
        'file_name_confirmed',
        'storage_folder',
        'agency',
        'requires_spouse_documents',
        'account_type_id',
        'created_by',
        'status',
        'member_identification',
        'member_first_names',
        'member_last_names',
        'member_nationality',
        'member_address',
        'extracted_data',
        'submitted_at',
        'ai_review_status',
        'ai_review_score',
        'ai_review_result',
        'ai_reviewed_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_code';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where(function ($query) use ($value) {
            $query->where('public_code', $value)
                ->when(is_numeric($value), fn ($query) => $query->orWhere('id', $value));
        })
            ->when(
                auth()->check() && !auth()->user()->isAdministrator(),
                fn ($query) => $query->where('agency', auth()->user()->agency)
            )
            ->firstOrFail();
    }

    protected $casts = [
        'extracted_data' => 'array',
        'file_name_confirmed' => 'boolean',
        'requires_spouse_documents' => 'boolean',
        'submitted_at' => 'datetime',
        'ai_review_result' => 'array',
        'ai_reviewed_at' => 'datetime',
    ];

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function consent(): HasOne
    {
        return $this->hasOne(PersonalDataConsent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UploadedDocument::class);
    }

    public function externalEvidences(): HasMany
    {
        return $this->hasMany(ExternalCheckEvidence::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(SelectedAdditionalService::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ActionHistory::class);
    }

    public function operationalRecords(): HasMany
    {
        return $this->hasMany(OperationalCheckRecord::class);
    }
}
