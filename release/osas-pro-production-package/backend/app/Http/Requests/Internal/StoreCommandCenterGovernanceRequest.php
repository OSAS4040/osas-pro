<?php

namespace App\Http\Requests\Internal;

use App\Services\Intelligence\Phase7\CommandCenterGovernanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommandCenterGovernanceRequest extends FormRequest
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
            'governance_ref' => ['required', 'string', 'max:8192'],
            'action'         => ['required', 'string', Rule::in(CommandCenterGovernanceService::ACTIONS)],
            'note'           => ['nullable', 'string', 'max:'.CommandCenterGovernanceService::NOTE_MAX],
            'client_context' => ['nullable', 'array', 'max:20'],
        ];
    }
}
