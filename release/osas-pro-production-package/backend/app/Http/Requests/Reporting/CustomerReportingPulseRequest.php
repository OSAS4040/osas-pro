<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CustomerReportingPulseRequest extends FormRequest
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
            'from'         => ['required', 'date_format:Y-m-d'],
            'to'           => ['required', 'date_format:Y-m-d'],
            'customer_id'  => ['required', 'integer', 'min:1'],
            'branch_id'    => ['nullable', 'integer', 'min:1'],
            'user_id'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $fromRaw = $this->input('from');
            $toRaw = $this->input('to');
            if (! is_string($fromRaw) || ! is_string($toRaw)) {
                return;
            }
            try {
                $from = CarbonImmutable::createFromFormat('Y-m-d', $fromRaw);
                $to = CarbonImmutable::createFromFormat('Y-m-d', $toRaw);
            } catch (\Throwable) {
                return;
            }
            if ($from === false || $to === false) {
                return;
            }
            if ($to->lessThan($from)) {
                $v->errors()->add('to', 'The to date must be on or after from.');
            }
            $maxDays = (int) config('reporting.max_date_range_days', 120);
            $fromDay = $from->startOfDay();
            $toDay = $to->startOfDay();
            $inclusiveDays = $fromDay->diffInDays($toDay) + 1;
            if ($inclusiveDays > $maxDays) {
                $v->errors()->add('to', "Date range must not exceed {$maxDays} calendar days.");
            }
        });
    }
}
