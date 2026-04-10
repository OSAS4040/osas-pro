<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $e = trim((string) $this->input('email'));
            $e = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $e);
            $this->merge(['email' => Str::lower($e)]);
        }
    }

    public function rules(): array
    {
        return [
            'token'                 => ['required', 'string', 'max:128'],
            'email'                 => ['required', 'email', 'max:255'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
