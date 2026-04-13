<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'name_ar'    => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:20', 'unique:companies,tax_number'],
            'cr_number'  => ['nullable', 'string', 'max:20', 'unique:companies,cr_number'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string'],
            'city'       => ['nullable', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'size:3'],
            'currency'   => ['nullable', 'string', 'size:3'],
            'timezone'   => ['nullable', 'string', 'timezone'],
        ];
    }
}
