<?php

namespace App\Policies;

use App\Models\AccountOpening;
use App\Models\UploadedDocument;
use App\Models\User;

class UploadedDocumentPolicy
{
    public function view(User $user, UploadedDocument $document): bool
    {
        $opening = AccountOpening::query()->find($document->account_opening_id);

        return $opening && $user->canManageAccountOpening($opening);
    }

    public function download(User $user, UploadedDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function update(User $user, UploadedDocument $document): bool
    {
        return $this->view($user, $document);
    }
}
