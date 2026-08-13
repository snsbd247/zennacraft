<?php

namespace App\Modules\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModerateProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
