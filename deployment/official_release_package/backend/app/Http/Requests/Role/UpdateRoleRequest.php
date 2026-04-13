<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value ?? $this->user()?->role, ['owner', 'manager']);
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:80'],
            'description'   => ['nullable', 'string', 'max:500'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
