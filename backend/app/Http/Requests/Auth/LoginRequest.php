<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $e = $this->input('email');
            $raw = is_string($e) ? trim($e) : (is_scalar($e) ? trim((string) $e) : $e);
            if (is_string($raw)) {
                $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw);
            }
            $this->merge([
                'email' => is_string($raw) ? Str::lower($raw) : $raw,
            ]);
        }

        if ($this->has('password')) {
            $p = $this->input('password');
            $asString = is_string($p) ? $p : (is_scalar($p) ? (string) $p : $p);
            if (is_string($asString)) {
                $asString = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $asString);
            }
            $this->merge([
                'password' => $asString,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email'         => ['required', 'email', 'max:255'],
            'password'      => ['required', 'string', 'min:6'],
            'otp'           => ['nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'otp_challenge' => ['nullable', 'string', 'max:80'],
        ];
    }
}
