<?php

namespace App\Modules\Marketing\Http\Requests;

use App\Modules\Marketing\Services\MarketingAnalyticsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdSpendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('staff')->check();
    }

    public function rules(): array
    {
        return [
            'spend_date' => ['required', 'date'],
            'platform' => ['required', Rule::in(MarketingAnalyticsService::PLATFORMS)],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
