<?php

namespace App\Modules\LandingPage\Http\Requests;

use Closure;
use App\Modules\LandingPage\Models\LandingPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateLandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $landingPage = $this->route('landingPage');
        $reservedSlugRule = $this->reservedSlugRule();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $reservedSlugRule, Rule::unique('landing_pages', 'slug')->ignore($landingPage?->id)],
            'status' => ['required', 'in:active,inactive'],
            'template' => ['required', 'in:'.implode(',', array_keys(LandingPage::TEMPLATES))],
            'cta_text' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'hero_image' => ['nullable', 'image', 'max:6144'],
            'suggested_products' => ['nullable', 'array'],
            'suggested_products.*' => ['integer', 'exists:products,id'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:6144'],
            'clear_gallery' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'string', 'max:4000'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'show_reviews' => ['nullable', 'boolean'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
        ];
    }

    protected function reservedSlugRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (in_array(Str::slug((string) $value), $this->reservedSlugs(), true)) {
                $fail('The '.$attribute.' field is reserved for a system path.');
            }
        };
    }

    protected function reservedSlugs(): array
    {
        return [
            'studio',
            'admin',
            'health',
            'products',
            'categories',
            'product',
            'category',
            'checkout',
            'cart',
            'orders',
            'login',
            'register',
            'api',
            'storage',
        ];
    }
}
