<?php

namespace App\Http\Requests;

use App\Models\AccountOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opening = $this->route('opening');

        return $opening instanceof AccountOpening
            && ($this->user()?->canManageAccountOpening($opening) ?? false);
    }

    public function rules(): array
    {
        $opening = $this->route('opening');
        $requiresContributionDecision = $opening instanceof AccountOpening
            && in_array($opening->accountType?->slug, ['cuenta-ahorro-programado', 'cuenta-juridica'], true);

        return [
            'fondo_mortuorio' => ['required', Rule::in(['si', 'no'])],
            'tipo_vinculacion' => [
                Rule::requiredIf($requiresContributionDecision),
                'nullable',
                Rule::in(['socio', 'cliente']),
            ],
        ];
    }
}
