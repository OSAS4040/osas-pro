<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'manager']);
    }

    public function rules(): array
    {
        return [
            'name'                => ['sometimes', 'string', 'max:255'],
            'name_ar'             => ['nullable', 'string', 'max:255'],
            'code'                => ['nullable', 'string', 'max:20'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'address'             => ['nullable', 'string'],
            'city'                => ['nullable', 'string', 'max:100'],
            'is_active'           => ['nullable', 'boolean'],
            'cross_branch_access' => ['nullable', 'boolean'],
        ];
    }
}
