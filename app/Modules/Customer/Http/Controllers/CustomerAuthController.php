<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Http\Requests\RequestCustomerOtpRequest;
use App\Modules\Customer\Http\Requests\VerifyCustomerOtpRequest;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerOtpService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function __construct(
        private CustomerOtpService $otpService,
        private PhoneService $phoneService,
    ) {}

    public function showRequestOtp(): View
    {
        return view('storefront.customer-auth.request-otp');
    }

    public function requestOtp(RequestCustomerOtpRequest $request): RedirectResponse
    {
        $phone = $this->normalizeOrFail($request->validated('phone'));
        $customer = $this->findOrCreateCustomer($phone);

        $this->otpService->generateOtp($customer);

        session(['customer_otp_phone' => $phone]);

        return redirect()
            ->route('customer.otp.verify.form')
            ->with('status', 'A verification code has been sent to your phone.');
    }

    public function showVerifyOtp(): View
    {
        return view('storefront.customer-auth.verify-otp', [
            'phone' => session('customer_otp_phone'),
        ]);
    }

    public function verifyOtp(VerifyCustomerOtpRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $phone = $this->normalizeOrFail($data['phone']);
        $otp = $this->otpService->verifyOtp($phone, $data['otp']);

        if (! $otp->customer_id) {
            abort(403);
        }

        $request->session()->regenerate();

        $request->session()->put([
            'customer_id' => $otp->customer_id,
            'customer_otp_phone' => $phone,
        ]);

        return redirect()
            ->route('customer.dashboard')
            ->with('status', 'Customer session verified.');
    }

    public function logout(): RedirectResponse
    {
        session()->forget(['customer_id', 'customer_otp_phone']);
        session()->regenerateToken();

        return redirect()
            ->route('customer.login')
            ->with('status', 'Logged out.');
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
