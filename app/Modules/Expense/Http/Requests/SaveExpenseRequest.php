<?php

namespace App\Modules\Expense\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('staff')->check();
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('status', 'active'),
            ],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
