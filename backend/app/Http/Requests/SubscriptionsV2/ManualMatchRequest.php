<?php

declare(strict_types=1);

namespace App\Http\Requests\SubscriptionsV2;

use Illuminate\Foundation\Http\FormRequest;

final class ManualMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_transaction_id' => ['required', 'integer', 'exists:bank_transactions,id'],
        ];
    }
}
