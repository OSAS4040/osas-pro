<?php

declare(strict_types=1);

namespace App\Http\Requests\PlatformIntelligence;

use App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExecuteGuidedWorkflowRequest extends FormRequest
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
            'workflow_key' => ['required', 'string', Rule::in(GuidedWorkflowKey::values())],
            'idempotency_key' => ['required', 'uuid'],
            'confirm' => ['required', 'accepted'],
            'owner_ref' => ['nullable', 'string', 'max:190'],
            'decision_summary' => ['nullable', 'string', 'max:2000'],
            'rationale' => ['nullable', 'string', 'max:8000'],
            'expected_outcome' => ['nullable', 'string', 'max:2000'],
            'follow_up_required' => ['nullable', 'boolean'],
            'close_reason' => ['nullable', 'string', 'max:8000'],
            'escalate_reason' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
