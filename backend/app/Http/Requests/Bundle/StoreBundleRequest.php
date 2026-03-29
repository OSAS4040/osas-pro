<?php

namespace App\Http\Requests\Bundle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name'                  => ['required', 'string', 'max:200'],
            'name_ar'               => ['nullable', 'string', 'max:200'],
            'code'                  => [
                'nullable', 'string', 'max:50',
                Rule::unique('bundles', 'code')->where('company_id', $companyId),
            ],
            'description'           => ['nullable', 'string'],
            'base_price'            => ['nullable', 'numeric', 'min:0'],
            'override_item_prices'  => ['nullable', 'boolean'],
            'is_active'             => ['nullable', 'boolean'],
            'branch_id'             => ['nullable', 'integer', 'exists:branches,id'],
            'items'                 => ['nullable', 'array', 'min:1'],
            'items.*.item_type'     => ['required', 'in:service,product'],
            'items.*.service_id'    => ['nullable', 'integer', 'exists:services,id'],
            'items.*.product_id'    => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price_override' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'         => ['nullable', 'string'],
            'items.*.sort_order'    => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->items ?? [] as $i => $item) {
                if ($item['item_type'] === 'service' && empty($item['service_id'])) {
                    $validator->errors()->add("items.{$i}.service_id", 'service_id is required when item_type is service.');
                }
                if ($item['item_type'] === 'product' && empty($item['product_id'])) {
                    $validator->errors()->add("items.{$i}.product_id", 'product_id is required when item_type is product.');
                }
            }
        });
    }
}
