<?php

declare(strict_types=1);

namespace App\Http\Requests\SubscriptionsV2;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitBankTransferRequest extends FormRequest
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
            'amount'                  => ['required', 'numeric', 'min:0.01'],
            'transfer_date'           => ['required', 'date'],
            'transfer_time'           => ['nullable', 'date_format:H:i'],
            'bank_name'               => ['required', 'string', 'max:190'],
            'sender_name'             => ['nullable', 'string', 'max:190'],
            'sender_account_masked'   => ['nullable', 'string', 'max:64'],
            'bank_reference'          => ['nullable', 'string', 'max:190'],
            'receipt_path'            => ['nullable', 'string', 'max:500'],
            'receipt_original_name'   => ['nullable', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
        ];
    }
}
