<?php

namespace App\Modules\Courier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'courier_provider_id' => ['nullable', Rule::exists('courier_providers', 'id')->where('status', 'active')],
            'tracking_number' => ['nullable', 'string', 'max:255', Rule::unique('shipments', 'tracking_number')],
            'consignment_id' => ['nullable', 'string', 'max:255', Rule::unique('shipments', 'consignment_id')],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'courier_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
