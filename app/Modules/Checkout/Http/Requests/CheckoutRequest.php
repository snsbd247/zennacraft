<?php

namespace App\Modules\Checkout\Http\Requests;

use App\Modules\Checkout\Services\DeliveryChargeService;
use App\Modules\Checkout\Services\PaymentGatewayService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'cart_checkout' => ['nullable', 'boolean'],
            'product_id' => ['required_unless:cart_checkout,1', 'nullable', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'delivery_zone' => ['required', Rule::in(array_keys(DeliveryChargeService::ZONES))],
            'payment_method' => ['nullable', 'string', Rule::in(array_merge(['cod'], array_keys(app(PaymentGatewayService::class)->enabledGateways())))],
        ];
    }
}
