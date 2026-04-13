<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;

class AssignVerticalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vertical_profile_code' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

