<?php

namespace App\Modules\Storefront\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCmsPageRequest extends StoreCmsPageRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $page = $this->route('cmsPage');
        $rules['slug'] = ['nullable', 'string', 'max:255', $this->reservedSlugRule(), Rule::unique('cms_pages', 'slug')->ignore($page?->id)];

        return $rules;
    }
}
