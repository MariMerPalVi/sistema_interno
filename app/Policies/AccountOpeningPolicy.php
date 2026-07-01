<?php

namespace App\Policies;

use App\Models\AccountOpening;
use App\Models\User;

class AccountOpeningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canUseAccountOpenings() || $user->isAdministrator();
    }

    public function view(User $user, AccountOpening $opening): bool
    {
        return $user->canViewAccountOpening($opening);
    }

    public function manage(User $user, AccountOpening $opening): bool
    {
        return $user->canManageAccountOpening($opening);
    }
}
