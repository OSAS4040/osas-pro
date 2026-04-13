<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            'branch_id'  => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'numeric', 'min:0.0001'],
            'type'       => ['required', 'string', 'in:add,subtract,set'],
            'unit_id'    => ['nullable', 'integer', 'exists:units,id'],
            'unit_cost'  => ['nullable', 'numeric', 'min:0'],
            'note'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
