<?php

namespace App\Modules\Marketing\Http\Requests;

use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Support\MarketingCampaignRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketingCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campaign = $this->route('campaign');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('marketing_campaigns', 'slug')->ignore($campaign?->id)],
            'description' => ['nullable', 'string'],
            'campaign_type' => ['required', Rule::in(MarketingCampaignRegistry::keys())],
            'status' => ['required', Rule::in(MarketingCampaign::STATUSES)],
            'audience_type' => ['required', Rule::in(MarketingCampaign::AUDIENCE_TYPES)],
            'marketing_segment_id' => ['nullable', 'required_if:audience_type,segment', 'exists:marketing_segments,id'],
            'customer_ids' => ['nullable', 'string'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
