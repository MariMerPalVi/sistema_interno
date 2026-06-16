<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['username', 'role_id', 'agency', 'name', 'email', 'password', 'active'];

    protected $hidden = ['password', 'remember_token'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function accountOpenings(): HasMany
    {
        return $this->hasMany(AccountOpening::class, 'created_by');
    }

    public function isAdministrator(): bool
    {
        return $this->role?->name === 'administrador';
    }

    public function agencyName(): string
    {
        return config("opening.agencies.{$this->agency}.name", $this->agency);
    }
}
