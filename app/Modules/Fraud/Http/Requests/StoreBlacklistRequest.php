<?php

namespace App\Modules\Fraud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlacklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('staff')->check()
            && auth()->guard('staff')->user()->canAccess('customer.blacklist.manage');
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
