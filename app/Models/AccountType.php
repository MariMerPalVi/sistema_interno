<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends Model
{
    protected $fillable = ['name', 'slug', 'notes', 'requires_spouse_docs', 'active'];

    public function requirements(): HasMany
    {
        return $this->hasMany(AccountTypeRequirement::class)->where('active', true)->orderBy('sort_order');
    }
}
