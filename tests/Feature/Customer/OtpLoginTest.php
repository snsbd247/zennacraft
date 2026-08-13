<?php

namespace Tests\Feature\Customer;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Customer\Models\CustomerOtp;
use Database\Seeders\GeneralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // communication.sms_enabled defaults to true only once seeded (see
        // A5.4) — an unseeded test database has no settings row at all, so
        // seed the real baseline seeder rather than hand-setting the flag,
        // to prove the actual fresh-install path works end to end.
        $this->seed(GeneralSettingsSeeder::class);

        config(['sms.driver' => 'log']);
    }

    public function test_verify_otp_page_renders(): void
    {
        // Guards the Blade compile bug that 500'd this page: an @if glued to a
        // word ("code@if(...)") is left as literal text while its @endif still
        // compiles, producing an orphan endif. Render it both with and without
        // a phone in the session.
        $this->withSession(['customer_otp_phone' => '01712345621'])
            ->get(route('customer.otp.verify.form'))
            ->assertOk()
            ->assertSee('Enter your code')
            ->assertSee('01712345621');

        $this->get(route('customer.otp.verify.form'))
            ->assertOk()
            ->assertSee('Enter your code');
    }

    public function test_customer_can_request_and_verify_otp_and_sms_is_dispatched(): void
    {
        $phone = '01712345621';

        $response = $this->post('/customer/request-otp', ['phone' => $phone]);

        $response->assertRedirect(route('customer.otp.verify.form'));

        $otp = CustomerOtp::where('phone', $phone)->latest()->first();
        $this->assertNotNull($otp, 'Expected a CustomerOtp row to be created.');

        // The regression this guards against: OTP being written to the DB
        // but never handed to the SMS pipeline. Assert a CommunicationMessage
        // was created AND actually dispatched/sent (QUEUE_CONNECTION=sync in
        // testing runs the job inline), not just that the OTP row exists.
        $message = CommunicationMessage::where('channel', 'sms')
            ->where('template', 'customer_otp')
            ->latest()
            ->first();

        $this->assertNotNull($message, 'Expected the OTP to be sent through the communication pipeline.');
        $this->assertSame('sent', $message->status, 'Expected the log SMS driver to report the message as sent.');
        $this->assertStringContainsString($otp->otp_code, $message->body, 'The real OTP code must be in the message body sent to the driver.');

        $verifyResponse = $this->post('/customer/verify-otp', [
            'phone' => $phone,
            'otp' => $otp->otp_code,
        ]);

        $verifyResponse->assertRedirect(route('customer.dashboard'));
        $this->assertNotNull(session('customer_id'), 'Expected the customer session to be established after verification.');
    }
}
