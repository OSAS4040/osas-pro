<?php

declare(strict_types=1);

namespace App\Http\Requests\PlatformIntelligence;

use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePlatformDecisionLogEntryRequest extends FormRequest
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
        $types = PlatformDecisionType::values();

        return [
            'decision_type' => ['required', 'string', Rule::in($types)],
            'decision_summary' => ['required', 'string', 'min:3', 'max:2000'],
            'rationale' => ['required', 'string', 'min:3', 'max:8000'],
            'expected_outcome' => ['nullable', 'string', 'max:2000'],
            'evidence_refs' => ['nullable', 'array', 'max:50'],
            'evidence_refs.*' => ['string', 'max:512'],
            'linked_notes' => ['nullable', 'array', 'max:50'],
            'linked_notes.*' => ['string', 'max:512'],
            'linked_signals' => ['nullable', 'array', 'max:50'],
            'linked_signals.*' => ['string', 'max:256'],
            'follow_up_required' => ['nullable', 'boolean'],
        ];
    }
}
