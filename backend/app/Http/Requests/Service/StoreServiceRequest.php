<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name'               => ['required', 'string', 'max:200'],
            'name_ar'            => ['nullable', 'string', 'max:200'],
            'code'               => [
                'nullable', 'string', 'max:50',
                Rule::unique('services', 'code')->where('company_id', $companyId),
            ],
            'description'        => ['nullable', 'string'],
            'base_price'         => ['required', 'numeric', 'min:0'],
            'tax_rate'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estimated_minutes'  => ['nullable', 'integer', 'min:1'],
            'is_active'          => ['nullable', 'boolean'],
            'branch_id'          => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }
}
