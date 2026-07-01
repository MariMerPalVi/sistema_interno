<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUseAccountOpenings() ?? false;
    }

    public function rules(): array
    {
        return [
            'account_type_id' => ['required', 'exists:account_types,id'],
        ];
    }
}
