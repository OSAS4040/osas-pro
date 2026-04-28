<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Support\StaffNav\StaffNavKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePlatformTenantNavHideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope'       => ['required', 'string', Rule::in(['company', 'user', 'customer'])],
            'nav_key'     => ['required', 'string', 'max:190'],
            'company_id'  => ['nullable', 'integer', 'exists:companies,id'],
            'user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            /** @var \Illuminate\Validation\Validator $v */
            $scope = (string) $this->input('scope', '');
            $key   = (string) $this->input('nav_key', '');

            if ($scope === 'company' || $scope === 'user') {
                if (! StaffNavKey::isValidStaffKey($key)) {
                    $v->errors()->add('nav_key', 'يجب أن يكون المفتاح بصيغة staff.nav.*');
                }
            } elseif ($scope === 'customer') {
                if (! StaffNavKey::isValidCustomerKey($key)) {
                    $v->errors()->add('nav_key', 'يجب أن يكون المفتاح بصيغة customer.nav.*');
                }
            }

            $companyId  = $this->input('company_id');
            $userId     = $this->input('user_id');
            $customerId = $this->input('customer_id');

            if ($scope === 'company' && ($companyId === null || (int) $companyId <= 0)) {
                $v->errors()->add('company_id', 'مطلوب عند النطاق company');
            }
            if ($scope === 'user' && ($userId === null || (int) $userId <= 0)) {
                $v->errors()->add('user_id', 'مطلوب عند النطاق user');
            }
            if ($scope === 'customer' && ($customerId === null || (int) $customerId <= 0)) {
                $v->errors()->add('customer_id', 'مطلوب عند النطاق customer');
            }

            if ($scope !== 'company' && $companyId !== null && $companyId !== '') {
                $v->errors()->add('company_id', 'يُستخدم فقط مع النطاق company');
            }
            if ($scope !== 'user' && $userId !== null && $userId !== '') {
                $v->errors()->add('user_id', 'يُستخدم فقط مع النطاق user');
            }
            if ($scope !== 'customer' && $customerId !== null && $customerId !== '') {
                $v->errors()->add('customer_id', 'يُستخدم فقط مع النطاق customer');
            }
        });
    }
}
