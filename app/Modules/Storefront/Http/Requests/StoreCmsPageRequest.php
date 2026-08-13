<?php

namespace App\Modules\Storefront\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $this->reservedSlugRule(), 'unique:cms_pages,slug'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
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
            'customer',
            'track',
            'api',
            'storage',
            'pages',
        ];
    }
}
