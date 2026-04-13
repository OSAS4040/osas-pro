<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class GlobalOperationsFeedRequest extends FormRequest
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
        $maxPer = (int) config('reporting.global_feed_max_per_page', 100);

        return [
            'from'                => ['required', 'date_format:Y-m-d'],
            'to'                  => ['required', 'date_format:Y-m-d'],
            'company_id'          => ['nullable', 'integer', 'min:1'],
            'branch_id'           => ['nullable', 'integer', 'min:1'],
            'customer_id'         => ['nullable', 'integer', 'min:1'],
            'user_id'             => ['nullable', 'integer', 'min:1'],
            'type'                => ['nullable', 'string', 'in:work_order,invoice,payment,ticket'],
            'types'               => ['nullable', 'array'],
            'types.*'             => ['string', 'in:work_order,invoice,payment,ticket'],
            'statuses'            => ['nullable', 'array'],
            'statuses.*'          => ['string', 'max:64'],
            'attention_level'     => ['nullable', 'string', 'in:normal,watch,important,critical'],
            'include_financial'   => ['nullable', 'boolean'],
            'page'                => ['nullable', 'integer', 'min:1'],
            'per_page'            => ['nullable', 'integer', 'min:1', 'max:'.$maxPer],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('types') && is_string($this->input('types'))) {
            $raw = (string) $this->input('types');
            $this->merge([
                'types' => array_values(array_filter(array_map('trim', explode(',', $raw)))),
            ]);
        }
        if ($this->has('statuses') && is_string($this->input('statuses'))) {
            $raw = (string) $this->input('statuses');
            $this->merge([
                'statuses' => array_values(array_filter(array_map('trim', explode(',', $raw)))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        if ($key !== null) {
            return parent::validated($key, $default);
        }
        /** @var array<string, mixed> $v */
        $v = parent::validated();
        if (! empty($v['type']) && empty($v['types'])) {
            $v['types'] = [(string) $v['type']];
        }

        return $v;
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
