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

            $collision = User::withoutGlobalScope('tenant')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get(['id', 'phone'])
                ->contains(function (User $u) use ($variants): bool {
                    $uv = PhoneNormalizer::comparisonVariants((string) $u->getRawOriginal('phone'));

                    return count(array_intersect($variants, $uv)) > 0;
                });

            if ($collision) {
                $v->errors()->add('phone', 'رقم الجوال مسجّل مسبقاً.');
            }
        });
    }
}
