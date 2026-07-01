<?php

namespace App\Http\Requests;

use App\Models\AccountOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExternalEvidenceRequest extends FormRequest
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
        return [
            'evidence_images' => ['nullable', 'array'],
            'evidence_images.*' => ['nullable', 'array'],
            'evidence_images.*.*' => ['nullable', 'string'],
            'results' => ['required', 'array'],
            'results.*' => ['required', 'array'],
            'results.*.*' => [Rule::in(['sin_novedad', 'con_observacion', 'no_aplica', 'pendiente'])],
            'observations' => ['nullable', 'array'],
            'observations.*' => ['nullable', 'array'],
            'observations.*.*' => ['nullable', 'string', 'max:500'],
            'company_check_applicable' => ['nullable', 'boolean'],
            'include_system_360' => ['nullable', 'boolean'],
            'system_360_file' => ['nullable', 'file', 'mimes:pdf', 'max:'.self::MAX_FILE_KB],
        ];
    }
}
