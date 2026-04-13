<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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

        if ($this->has('identifier')) {
            $id = $this->input('identifier');
            $raw = is_string($id) ? trim($id) : (is_scalar($id) ? trim((string) $id) : $id);
            if (is_string($raw)) {
                $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw);
            }
            $merged = ['identifier' => is_string($raw) ? $raw : $raw];
            if (is_string($raw) && str_contains($raw, '@')) {
                $merged['email'] = Str::lower($raw);
            }
            $this->merge($merged);
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

        foreach (['device_name', 'device_type', 'fcm_token'] as $key) {
            if (! $this->has($key)) {
                continue;
            }
            $v = $this->input($key);
            if (is_string($v)) {
                $this->merge([$key => trim($v)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'email'         => ['required_without:identifier', 'nullable', 'string', 'email', 'max:255'],
            'identifier'    => ['required_without:email', 'nullable', 'string', 'max:255'],
            'password'      => ['required', 'string', 'min:6'],
            'otp'           => ['nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'otp_challenge' => ['nullable', 'string', 'max:80'],
            'device_name'   => ['required_with:identifier', 'nullable', 'string', 'max:120'],
            'device_type'   => ['required_with:identifier', 'nullable', 'string', Rule::in(['android', 'ios', 'ipados', 'unknown'])],
            'fcm_token'     => ['nullable', 'string', 'max:512'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $id = $this->input('identifier');
            if (! is_string($id) || $id === '') {
                return;
            }
            if (str_contains($id, '@') && ! filter_var($id, FILTER_VALIDATE_EMAIL)) {
                $validator->errors()->add('identifier', __('validation.email', ['attribute' => 'identifier']));
            }
        });
    }
}
