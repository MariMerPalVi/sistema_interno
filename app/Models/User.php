<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'username',
        'role_id',
        'agency',
        'name',
        'email',
        'password',
        'active',
        'must_change_password',
        'password_changed_at',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'active' => 'boolean',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
    ];

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
        return $this->roleName() === 'administrador';
    }

    public function canReviewConsents(): bool
    {
        return $this->active && $this->roleName() === 'abogada';
    }

    public function roleName(): ?string
    {
        return $this->role?->name;
    }

    public function isLocked(): bool
    {
        return $this->locked_until?->isFuture() ?? false;
    }

    public function registerFailedLogin(): void
    {
        $attempts = min(((int) $this->failed_login_attempts) + 1, 255);

        $this->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : $this->locked_until,
        ])->save();
    }

    public function clearLoginLock(): void
    {
        $this->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();
    }

    public function canUseAccountOpenings(): bool
    {
        return $this->active && in_array($this->roleName(), ['administrador', 'asesor', 'supervisor'], true);
    }

    public function canUseDataUpdates(): bool
    {
        return $this->active && in_array($this->roleName(), ['administrador', 'asesor', 'supervisor'], true);
    }

    public function canViewAccountOpening(AccountOpening $opening): bool
    {
        if ($this->isAdministrator() || $this->canReviewConsents()) {
            return true;
        }

        return $this->canUseAccountOpenings() && $this->agency === $opening->agency;
    }

    public function canManageAccountOpening(AccountOpening $opening): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        return $this->canUseAccountOpenings() && $this->agency === $opening->agency;
    }

    public function canViewConsent(PersonalDataConsent $consent): bool
    {
        if ($this->isAdministrator() || $this->canReviewConsents()) {
            return true;
        }

        return $consent->opening
            && $this->canManageAccountOpening($consent->opening);
    }

    public function canAccessProtectedAssets(): bool
    {
        return $this->canUseAccountOpenings();
    }

    public function agencyName(): string
    {
        return config("opening.agencies.{$this->agency}.name", $this->agency);
    }
}
