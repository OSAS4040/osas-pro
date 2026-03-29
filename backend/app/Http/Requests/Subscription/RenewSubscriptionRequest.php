<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'plan'     => ['required', 'string', 'exists:plans,slug'],
            'months'   => ['nullable', 'integer', 'min:1', 'max:24'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
