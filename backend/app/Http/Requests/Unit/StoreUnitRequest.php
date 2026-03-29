<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value ?? $this->user()?->role, ['owner', 'manager']);
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name'      => ['required', 'string', 'max:80'],
            'name_ar'   => ['nullable', 'string', 'max:80'],
            'symbol'    => [
                'required', 'string', 'max:20',
                Rule::unique('units', 'symbol')->where('company_id', $companyId),
            ],
            'symbol_ar' => ['nullable', 'string', 'max:20'],
            'type'      => ['nullable', 'string', 'in:quantity,weight,volume,length,time'],
            'is_base'   => ['nullable', 'boolean'],
        ];
    }
}
