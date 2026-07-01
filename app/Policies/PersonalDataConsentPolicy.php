<?php

namespace App\Policies;

use App\Models\PersonalDataConsent;
use App\Models\User;

class PersonalDataConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canReviewConsents() || $user->isAdministrator();
    }

    public function view(User $user, PersonalDataConsent $consent): bool
    {
        return $user->canViewConsent($consent);
    }

    public function review(User $user, PersonalDataConsent $consent): bool
    {
        return $user->canReviewConsents();
    }
}
