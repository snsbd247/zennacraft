<?php

namespace App\Modules\AdminAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffUser = $this->route('staffUser');

        return [
            'name' => 'required|string',
            // Scoped to non-deleted rows — see StoreStaffRequest.
            'email' => ['required', 'email', Rule::unique('staff_users', 'email')->ignore($staffUser->id)->where(fn ($query) => $query->whereNull('deleted_at'))],
            'phone' => ['nullable', Rule::unique('staff_users', 'phone')->ignore($staffUser->id)->where(fn ($query) => $query->whereNull('deleted_at'))],
            'password' => 'nullable|min:8',
            'status' => 'required|in:active,inactive',
            'role' => 'required|exists:roles,slug',
        ];
    }
}
