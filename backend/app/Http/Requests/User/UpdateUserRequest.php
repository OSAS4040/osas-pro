<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'manager']);
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'role'      => ['sometimes', 'string', 'in:owner,manager,cashier,accountant,technician,viewer'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
            'password'  => ['nullable', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
