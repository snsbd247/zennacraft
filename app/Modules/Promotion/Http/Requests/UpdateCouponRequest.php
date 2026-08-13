<?php

namespace App\Modules\Promotion\Http\Requests;

use App\Modules\Promotion\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('staff')->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in(Coupon::DISCOUNT_TYPES)],
            'discount_value' => ['nullable', 'required_unless:discount_type,free_shipping', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'applies_to' => ['required', Rule::in(Coupon::APPLIES_TO)],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $appliesTo = $this->input('applies_to', 'all');
            $targetIds = collect($this->input('target_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($appliesTo === 'all') {
                return;
            }

            if ($targetIds->isEmpty()) {
                $validator->errors()->add('target_ids', 'Select at least one coupon target.');

                return;
            }

            $table = match ($appliesTo) {
                'product' => 'products',
                'category' => 'categories',
                'landing_page' => 'landing_pages',
                default => null,
            };

            if (! $table) {
                return;
            }

            $existing = DB::table($table)->whereIn('id', $targetIds)->count();

            if ($existing !== $targetIds->count()) {
                $validator->errors()->add('target_ids', 'One or more selected coupon targets are invalid.');
            }
        });
    }
}
