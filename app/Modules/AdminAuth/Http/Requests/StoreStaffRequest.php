<?php

namespace App\Modules\AdminAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            // Scoped to non-deleted rows: staff_users is soft-deleted now,
            // and the unique DB constraint was dropped in favor of this
            // (see the soft-deletes migration for why).
            'email' => ['required', 'email', Rule::unique('staff_users', 'email')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'phone' => ['nullable', Rule::unique('staff_users', 'phone')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'password' => 'required|min:8',
            'status' => 'required|in:active,inactive',
            'role' => 'required|exists:roles,slug',
        ];
    }
}
