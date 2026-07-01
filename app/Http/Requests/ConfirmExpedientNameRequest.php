<?php

namespace App\Http\Requests;

use App\Models\AccountOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmExpedientNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opening = $this->route('opening');

        return $opening instanceof AccountOpening
            && ($this->user()?->canManageAccountOpening($opening) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'file_name' => trim((string) $this->input('file_name')),
        ]);
    }

    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:120', Rule::unique('account_openings', 'file_name')],
        ];
    }

    public function messages(): array
    {
        return [
            'file_name.unique' => 'Ya existe un expediente con este número o nombre en la cooperativa.',
        ];
    }
}
