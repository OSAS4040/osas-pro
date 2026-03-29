<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'manager']);
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
            'role'      => ['required', 'string', 'in:owner,manager,cashier,accountant,technician,viewer'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
