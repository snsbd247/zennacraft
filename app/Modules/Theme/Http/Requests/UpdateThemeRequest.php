<?php

namespace App\Modules\Theme\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hexColorRule = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];
        $safeUrlRule = $this->safeUrlRule();

        return [
            'primary_color' => $hexColorRule,
            'secondary_color' => $hexColorRule,
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],
            'hero_button_text' => ['nullable', 'string', 'max:255'],
            'hero_button_url' => ['nullable', 'string', 'max:255', $safeUrlRule],
            'hero_image_id' => ['nullable', 'exists:media,id'],
            'homepage_show_hero_slider' => ['nullable', 'boolean'],
            'homepage_show_categories' => ['nullable', 'boolean'],
            'homepage_show_top_selling' => ['nullable', 'boolean'],
            'homepage_show_collections' => ['nullable', 'boolean'],
            'homepage_show_artisan_story' => ['nullable', 'boolean'],
            'homepage_show_craft_process' => ['nullable', 'boolean'],
            'homepage_show_reviews' => ['nullable', 'boolean'],
            'homepage_show_faq' => ['nullable', 'boolean'],
            'homepage_show_newsletter' => ['nullable', 'boolean'],
            'footer_text' => ['nullable', 'string'],
            'footer_description' => ['nullable', 'string'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'footer_menu' => ['nullable', 'string'],
            'show_search' => ['nullable', 'boolean'],
            'show_wishlist' => ['nullable', 'boolean'],
            'show_tracking' => ['nullable', 'boolean'],
            'show_account' => ['nullable', 'boolean'],
            'show_newsletter' => ['nullable', 'boolean'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    protected function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, callable $fail): void {
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
