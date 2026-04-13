<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // External API calls are pre-authenticated by auth.apikey middleware.
        return $this->attributes->get('api_key') !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|integer',
            'customer_type' => 'nullable|in:b2c,b2b',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'payment' => 'nullable|array',
            'payment.method' => 'required_with:payment|string',
            'payment.amount' => 'required_with:payment|numeric|min:0',
        ];
    }
}
