<?php

namespace App\Modules\Automation\Http\Requests;

use App\Modules\Automation\Models\AutomationWorkflow;
use App\Modules\Automation\Support\AutomationActionRegistry;
use App\Modules\Automation\Support\AutomationConditionRegistry;
use App\Modules\Automation\Support\AutomationTriggerRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomationWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'conditions_json' => $this->decodeJsonField('conditions_json'),
            'actions_json' => $this->decodeJsonField('actions_json'),
        ]);
    }

    public function rules(): array
    {
        $workflow = $this->route('workflow');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('automation_workflows', 'slug')->ignore($workflow?->id)],
            'description' => ['nullable', 'string'],
            'trigger_key' => ['required', Rule::in(AutomationTriggerRegistry::keys())],
            'status' => ['required', Rule::in(AutomationWorkflow::STATUSES)],
            'conditions_json' => ['nullable', 'array'],
            'conditions_json.*.key' => ['nullable', Rule::in(AutomationConditionRegistry::keys())],
            'conditions_json.*.condition_key' => ['nullable', Rule::in(AutomationConditionRegistry::keys())],
            'actions_json' => ['nullable', 'array'],
            'actions_json.*.key' => ['nullable', Rule::in(AutomationActionRegistry::keys())],
            'actions_json.*.action_key' => ['nullable', Rule::in(AutomationActionRegistry::keys())],
        ];
    }

    protected function decodeJsonField(string $key): ?array
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
