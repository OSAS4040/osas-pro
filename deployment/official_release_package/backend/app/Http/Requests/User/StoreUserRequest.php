<?php

namespace App\Http\Requests\User;

use App\Models\Company;
use App\Support\TenantBusinessFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole(['owner', 'manager']);
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) use ($companyId) {
                    if (\App\Models\User::where('company_id', $companyId)->where('email', $value)->exists()) {
                        $fail('This email is already registered in your company.');
                    }
                },
            ],
            'password'  => ['required', Password::min(8)->mixedCase()->numbers()],
            'phone'     => ['nullable', 'string', 'max:30'],
            'role'      => ['required', 'string', 'in:owner,manager,staff,cashier,accountant,technician,viewer'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'org_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('org_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $company = Company::query()->find((int) $this->user()->company_id);
                    if ($company === null || ! TenantBusinessFeatures::isEnabled($company, 'org_structure')) {
                        $fail(__('هيكل القطاعات غير مفعّل لملف نشاط منشأتك.'));
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
