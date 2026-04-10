<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole(['owner', 'manager']);
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'name_ar'             => ['nullable', 'string', 'max:255'],
            'code'                => ['nullable', 'string', 'max:20'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'address'             => ['nullable', 'string'],
            'city'                => ['nullable', 'string', 'max:100'],
            'latitude'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'           => ['nullable', 'numeric', 'between:-180,180'],
            'is_main'             => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
            'status'              => ['nullable', 'string', 'in:active,inactive'],
            'cross_branch_access' => ['nullable', 'boolean'],
            'opening_hours'       => ['nullable', 'array'],
        ];
    }
}
