<?php

namespace App\Http\Requests\Platform;

use App\Support\SaasPlatformAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlatformAnnouncementBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SaasPlatformAccess::isPlatformOperator($this->user());
    }

    public function rules(): array
    {
        return [
            'is_enabled'  => ['required', 'boolean'],
            'title'       => ['nullable', 'string', 'max:200'],
            'message'     => ['nullable', 'string', 'max:2000'],
            'link_url'    => ['nullable', 'string', 'max:2048'],
            'link_text'   => ['nullable', 'string', 'max:120'],
            'variant'     => ['nullable', 'string', 'in:info,success,warning,promo'],
            'dismissible' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $enabled = $this->boolean('is_enabled');
            if ($enabled) {
                $msg = trim((string) $this->input('message', ''));
                if ($msg === '') {
                    $v->errors()->add('message', 'نص الإعلان مطلوب عند تفعيل الشريط.');
                }
            }
        });
    }
}
