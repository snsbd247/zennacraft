<?php

namespace App\Modules\Customer\Services;

use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerOtp;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Validation\ValidationException;

class CustomerOtpService
{
    public function __construct(
        private PhoneService $phoneService,
        private CommunicationService $communicationService,
        private SettingService $settingService,
    ) {}

    public function generateOtp(Customer $customer): CustomerOtp
    {
        $otpSmsEnabled = filter_var($this->settingService->get('sms', 'notify_otp', true), FILTER_VALIDATE_BOOLEAN);

        if (! $otpSmsEnabled || ! $this->communicationService->channelEnabled('sms')) {
            throw ValidationException::withMessages([
                'phone' => 'Unable to send a verification code right now. Please try again shortly.',
            ]);
        }

        $otp = CustomerOtp::create([
            'customer_id' => $customer->id,
            'phone' => $this->phoneService->normalize($customer->phone),
            'otp_code' => $this->generateCode(),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // If the SMS can't actually be sent, don't leave a dangling code the
        // customer will never receive — remove it and surface the error.
        if (! $this->dispatchOtpSms($customer, $otp)) {
            $otp->delete();

            throw ValidationException::withMessages([
                'phone' => 'Unable to send a verification code right now. Please try again shortly.',
            ]);
        }

        return $otp;
    }

    protected function dispatchOtpSms(Customer $customer, CustomerOtp $otp): bool
    {
        $message = $this->communicationService->createFromTemplate(
            'sms',
            $otp->phone,
            'customer_otp',
            ['otp_code' => $otp->otp_code],
            [
                'customer_id' => $customer->id,
                'trigger' => 'customer_otp',
            ]
        );

        // Send inline (not queued): this hosting has no always-on queue worker,
        // so a queued login OTP would sit unprocessed and never reach the
        // customer. send() records status/error and never throws.
        return $this->communicationService->send($message)->status === 'sent';
    }

    public function verifyOtp(string $phone, string $otp): CustomerOtp
    {
        $lookupValues = $this->phoneService->lookupValues($phone);

        $otpRecord = CustomerOtp::whereIn('phone', $lookupValues)
            ->whereNull('verified_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'otp' => 'No active OTP was found.',
            ]);
        }

        if ($otpRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => 'The OTP has expired.',
            ]);
        }

        if ($otpRecord->attempts >= 5) {
            throw ValidationException::withMessages([
                'otp' => 'Too many OTP attempts.',
            ]);
        }

        if ($otpRecord->otp_code !== $otp) {
            $otpRecord->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => 'The OTP is invalid.',
            ]);
        }

        $otpRecord->forceFill(['verified_at' => now()])->save();

        return $otpRecord->refresh();
    }

    public function cleanupExpiredOtps(): int
    {
        return CustomerOtp::where('expires_at', '<', now())->delete();
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
