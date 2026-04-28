<?php

declare(strict_types=1);

namespace App\Http\Requests\SubscriptionsV2;

use Illuminate\Foundation\Http\FormRequest;

final class UploadReceiptRequest extends FormRequest
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
            'receipt'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'bank_reference' => ['nullable', 'string', 'max:190'],
            'notes'          => ['nullable', 'string', 'max:5000'],
        ];
    }
}
