<?php

namespace App\Http\Requests;

use App\Models\AccountOpening;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opening = $this->route('opening');

        return $opening instanceof AccountOpening
            && ($this->user()?->canManageAccountOpening($opening) ?? false);
    }

    public function rules(): array
    {
        return [
            'member_identification' => ['nullable', 'digits:10'],
            'member_first_names' => ['nullable', 'string', 'max:120'],
            'member_last_names' => ['nullable', 'string', 'max:120'],
            'member_nationality' => ['nullable', 'string', 'max:80'],
            'member_address' => ['nullable', 'string', 'max:200'],
        ];
    }
}
