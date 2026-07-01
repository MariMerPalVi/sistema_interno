<?php

namespace App\Http\Requests;

use App\Models\AccountOpening;
use Illuminate\Foundation\Http\FormRequest;

class UploadConsentRequest extends FormRequest
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
            'signed_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['required', 'accepted'],
            'observations' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'manual_signature_confirmed.required' => 'Debe revisar visualmente el consentimiento y confirmar que contiene la firma.',
            'manual_signature_confirmed.accepted' => 'Debe confirmar manualmente que el consentimiento contiene firma.',
        ];
    }
}
