<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token'    => ['required', 'string', 'min:10', 'max:512'],
            'device_name'  => ['nullable', 'string', 'max:120'],
            'device_type'  => ['nullable', 'string', Rule::in(['android', 'ios', 'ipados', 'unknown'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('fcm_token') && is_string($this->input('fcm_token'))) {
            $this->merge(['fcm_token' => trim($this->input('fcm_token'))]);
        }
        foreach (['device_name', 'device_type'] as $k) {
            if ($this->has($k) && is_string($this->input($k))) {
                $this->merge([$k => trim((string) $this->input($k))]);
            }
        }
    }
}
