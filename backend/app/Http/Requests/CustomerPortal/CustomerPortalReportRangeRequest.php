<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerPortal;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CustomerPortalReportRangeRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 90;

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
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'product_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'org_unit_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            /** فلاتر تقرير الفواتير (بوابة العميل) */
            'min_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'payment_status' => ['sometimes', 'nullable', 'string', 'in:all,paid,unpaid,overdue,partial'],
            /** تقرير الهيكل التنظيمي: تصفية أنواع الوحدات المعروضة (مفصولة بفاصلة) */
            'org_unit_types' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');
            if (! is_string($from) || ! is_string($to) || trim($from) === '' || trim($to) === '') {
                return;
            }

            try {
                $fromDate = Carbon::parse($from)->startOfDay();
                $toDate = Carbon::parse($to)->endOfDay();
            } catch (\Throwable) {
                return;
            }

            $days = $fromDate->diffInDays($toDate);
            if ($days > self::MAX_RANGE_DAYS) {
                $validator->errors()->add(
                    'to',
                    sprintf('نطاق التقرير يتجاوز الحد الأقصى (%d يومًا).', self::MAX_RANGE_DAYS)
                );
            }
        });
    }
}
