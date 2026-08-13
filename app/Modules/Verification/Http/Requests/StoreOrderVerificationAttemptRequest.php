<?php

namespace App\Modules\Verification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderVerificationAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in([
                'verified',
                'callback_requested',
                'no_answer',
                'wrong_number',
                'customer_cancelled',
                'duplicate_order',
                'suspicious',
                'failed_verification',
                'call_later',
                'failed',
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'callback_reason' => ['nullable', 'string', 'max:500'],
            'next_follow_up_at' => ['nullable', 'required_if:outcome,call_later,callback_requested', 'date', 'after:now'],
            'communication_channel' => ['nullable', Rule::in(['sms', 'whatsapp', 'internal'])],
        ];
    }
}
