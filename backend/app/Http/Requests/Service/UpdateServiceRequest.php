<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $id        = $this->route('id');

        return [
            'name'              => ['sometimes', 'string', 'max:200'],
            'name_ar'           => ['nullable', 'string', 'max:200'],
            'code'              => [
                'nullable', 'string', 'max:50',
                Rule::unique('services', 'code')->where('company_id', $companyId)->ignore($id),
            ],
            'description'       => ['nullable', 'string'],
            'base_price'        => ['sometimes', 'numeric', 'min:0'],
            'tax_rate'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active'         => ['nullable', 'boolean'],
        ];
    }
}
