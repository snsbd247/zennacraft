<?php

namespace App\Modules\Api\V1\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerOtpService;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerAuthApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CustomerOtpService $otpService,
        private PhoneService $phoneService,
    ) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $phone = $this->normalizeOrFail($validated['phone']);
        $customer = $this->findOrCreateCustomer($phone);

        $this->otpService->generateOtp($customer);

        return $this->success([], 'OTP generated successfully.');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'digits:6'],
        ]);

        $phone = $this->normalizeOrFail($validated['phone']);
        $otp = $this->otpService->verifyOtp($phone, $validated['otp']);
        $customer = $otp->customer ?? $this->findOrCreateCustomer($phone);

        $token = $customer->createToken('customer-api-token', ['customer'])->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => new CustomerResource($customer),
        ], 'OTP verified successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'customer' => new CustomerResource($request->user()),
        ]);
    }

    protected function normalizeOrFail(string $phone): string
    {
        if (! $this->phoneService->isValidBangladeshMobile($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid phone number.',
            ]);
        }

        return $this->phoneService->normalize($phone);
    }

    protected function findOrCreateCustomer(string $phone): Customer
    {
        $customer = Customer::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $phone))->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'phone' => $phone,
            'name' => $phone,
        ]);
    }
}
