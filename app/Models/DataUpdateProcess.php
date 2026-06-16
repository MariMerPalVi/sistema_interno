<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataUpdateProcess extends Model
{
    protected $fillable = [
        'public_code',
        'file_name',
        'storage_folder',
        'agency',
        'created_by',
        'status',
        'member_identification',
        'member_name',
        'selected_changes',
        'current_data',
        'new_data',
        'observations',
        'submitted_at',
    ];

    protected $casts = [
        'selected_changes' => 'array',
        'current_data' => 'array',
        'new_data' => 'array',
        'submitted_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DataUpdateDocument::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DataUpdateHistory::class);
    }
}
