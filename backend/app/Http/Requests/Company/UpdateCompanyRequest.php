<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    private function roleValue(): string
    {
        $role = $this->user()?->role;

        if ($role instanceof \BackedEnum) {
            return (string) $role->value;
        }

        if ($role instanceof \UnitEnum) {
            return $role->name;
        }

        return (string) $role;
    }

    public function authorize(): bool
    {
        return in_array($this->roleValue(), ['owner', 'manager'], true);
    }

    public function rules(): array
    {
        $companyId = $this->route('company') ?? $this->user()->company_id;

        return [
            'name'       => ['sometimes', 'string', 'max:255'],
            'name_ar'    => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:20', Rule::unique('companies', 'tax_number')->ignore($companyId)],
            'cr_number'  => ['nullable', 'string', 'max:20', Rule::unique('companies', 'cr_number')->ignore($companyId)],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string'],
            'city'       => ['nullable', 'string', 'max:100'],
            'logo_url'   => ['nullable', 'url', 'max:500'],
            'settings'   => ['nullable', 'array'],
            'timezone'   => ['nullable', 'string', 'timezone'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
