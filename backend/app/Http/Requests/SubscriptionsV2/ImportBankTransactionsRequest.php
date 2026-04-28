<?php

declare(strict_types=1);

namespace App\Http\Requests\SubscriptionsV2;

use Illuminate\Foundation\Http\FormRequest;

final class ImportBankTransactionsRequest extends FormRequest
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
            'rows'                      => ['required', 'array', 'min:1'],
            'rows.*.amount'             => ['required', 'numeric'],
            'rows.*.transaction_date'   => ['required', 'date'],
            'rows.*.transaction_time'   => ['nullable', 'date_format:H:i'],
            'rows.*.currency'           => ['nullable', 'string', 'max:8'],
            'rows.*.sender_name'        => ['nullable', 'string', 'max:190'],
            'rows.*.bank_reference'    => ['nullable', 'string', 'max:190'],
            'rows.*.description'        => ['nullable', 'string', 'max:2000'],
        ];
    }
}
