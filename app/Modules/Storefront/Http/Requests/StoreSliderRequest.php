<?php

namespace App\Modules\Storefront\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
            'desktop_image_id' => ['nullable', 'exists:media,id'],
            'mobile_image_id' => ['nullable', 'exists:media,id'],
            'badge_text' => ['nullable', 'string', 'max:80'],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
                return;
            }

            $scheme = parse_url($value, PHP_URL_SCHEME);

            if (in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL)) {
                return;
            }

            $fail('The '.$attribute.' field must be a relative URL or an http/https URL.');
        };
    }
}
