<?php

namespace App\Http\Requests;

use App\Models\AccountOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadRequirementRequest extends FormRequest
{
    private const MAX_FILE_KB = 5120;

    public function authorize(): bool
    {
        $opening = $this->route('opening');

        return $opening instanceof AccountOpening
            && ($this->user()?->canManageAccountOpening($opening) ?? false);
    }

    public function rules(): array
    {
        $opening = $this->route('opening');

        return [
            'account_type_requirement_id' => [
                'required',
                Rule::exists('account_type_requirements', 'id')
                    ->where('account_type_id', $opening?->account_type_id),
            ],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'status' => ['required', Rule::in(['cargado', 'validado', 'rechazado'])],
            'observations' => ['nullable', 'string', 'max:500'],
        ];
    }
}
