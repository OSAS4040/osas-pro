<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone'        => ['required', 'string', 'max:30'],
            'timezone'     => ['nullable', 'string', 'timezone'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $raw = trim((string) $this->input('phone', ''));
            $variants = PhoneNormalizer::comparisonVariants($raw);
            if ($variants === []) {
                $v->errors()->add('phone', 'أدخل رقم جوال صالحاً.');

                return;
            }

            $normalized = PhoneNormalizer::normalizeForStorage($raw);
            $lookup = array_values(array_unique(array_filter([
                $normalized,
                ...$variants,
            ])));

            $collision = User::withoutGlobalScope('tenant')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereIn('phone', $lookup)
                ->exists();

            if ($collision) {
                $v->errors()->add('phone', 'رقم الجوال مسجّل مسبقاً.');
            }
        });
    }
}
