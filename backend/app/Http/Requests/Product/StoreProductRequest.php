<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('products.create');
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name'             => ['required', 'string', 'max:255'],
            'name_ar'          => ['nullable', 'string', 'max:255'],
            'product_type'     => ['nullable', Rule::enum(ProductType::class)],
            'category_id'      => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit_id'          => ['nullable', 'integer', 'exists:units,id'],
            'purchase_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'barcode'          => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'barcode')->where('company_id', $companyId),
            ],
            'sku'              => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('company_id', $companyId),
            ],
            'sale_price'       => ['required', 'numeric', 'min:0'],
            'cost_price'       => ['nullable', 'numeric', 'min:0'],
            'tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_taxable'       => ['nullable', 'boolean'],
            'track_inventory'  => ['nullable', 'boolean'],
            'meta'             => ['nullable', 'array'],
        ];
    }
}
