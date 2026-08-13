<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Communication\Services\Sms\SmsDriverManager;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Setting & Configuration pages that are settings forms (Marketing, Courier API,
 * Payment, SMS, Email, Google, Social Login, Order Verification Call, Invoice
 * Address, Delivery Charge). Each page is a data-driven schema stored via
 * SettingService; secrets are encrypted at rest. Every provider/section has an
 * "enabled" switch — that single settings flag is what the rest of the system
 * reads (e.g. PaymentGatewayService, AutoCallVerificationService), so turning it
 * on here turns the integration on everywhere, and off turns it off everywhere.
 */
class ConfigurationController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function show(string $page): View
    {
        $cfg = $this->pages()[$page] ?? abort(404);
        $values = [];
        foreach ($cfg['sections'] as $section) {
            if (! empty($section['enable'])) {
                $values[$section['enable']] = $this->settings->get($cfg['group'], $section['enable']);
            }
            foreach ($section['fields'] as $f) {
                $values[$f['key']] = ($f['type'] ?? 'text') === 'secret'
                    ? $this->settings->getEncrypted($cfg['group'], $f['key'])
                    : $this->settings->get($cfg['group'], $f['key']);
            }
        }

        return view('studio.config.form', ['page' => $page, 'cfg' => $cfg, 'values' => $values]);
    }

    public function save(Request $request, string $page): RedirectResponse
    {
        $cfg = $this->pages()[$page] ?? abort(404);

        foreach ($cfg['sections'] as $section) {
            if (! empty($section['enable'])) {
                $this->settings->set($cfg['group'], $section['enable'], $request->boolean($section['enable']), 'boolean');
            }
            foreach ($section['fields'] as $f) {
                $key = $f['key'];
                $type = $f['type'] ?? 'text';
                if ($type === 'checkbox') {
                    $this->settings->set($cfg['group'], $key, $request->boolean($key), 'boolean');
                } elseif ($type === 'secret') {
                    $this->settings->setEncrypted($cfg['group'], $key, (string) $request->input($key, ''));
                } else {
                    $this->settings->set($cfg['group'], $key, (string) $request->input($key, ''));
                }
            }
        }

        return redirect()->route('config.'.$page)->with('success', $cfg['title'].' saved.');
    }

    /**
     * Send one real SMS through the currently-saved provider and show the
     * provider's exact response. This turns an opaque "OTP never arrives"
     * into a precise diagnosis — a bad API key, no balance, or a
     * provider-side rejection (e.g. Alpha SMS's "Please recharge…") is
     * shown verbatim instead of the generic customer-facing message.
     */
    public function testSms(Request $request, SmsDriverManager $drivers): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $driverName = $drivers->driverName();
        $result = $drivers->driver()->send(
            $validated['phone'],
            'Zenna Craft: your SMS gateway is working. This is a test message.'
        );

        $html = view('studio.config.partials._sms-test-result', [
            'ok' => $result->sent,
            'provider' => $driverName,
            'message' => $result->message,
            'phone' => $validated['phone'],
        ])->render();

        return response()->json([
            'success' => true,
            'message' => $result->sent ? 'Test SMS sent.' : 'Provider rejected the message.',
            'regions' => ['sms-test-result' => $html],
        ]);
    }

    private function pages(): array
    {
        return [
            // group => 'general' (not 'marketing') is deliberate: the pixel
            // script in layouts/app.blade.php, FacebookTrackingService, and
            // SystemDiagnosticsService's health panel all read Facebook
            // settings from the 'general' group — this page must save to the
            // exact same place or the fields would silently do nothing.
            'marketing' => ['title' => 'Marketing', 'group' => 'general', 'sections' => [
                ['title' => 'Facebook Pixel', 'enable' => 'facebook_pixel_enabled', 'desc' => 'Client-side browser tracking (PageView, ViewContent, InitiateCheckout, Purchase) — loads the Meta pixel script on every storefront page.', 'fields' => [
                    ['key' => 'facebook_pixel_id', 'label' => 'Facebook Pixel ID', 'type' => 'text'],
                ]],
                ['title' => 'Facebook Conversion API', 'enable' => 'facebook_capi_enabled', 'desc' => 'Server-side event tracking, sent alongside the pixel — more reliable (works past ad-blockers, iOS 14.5+ tracking limits) and required for accurate attribution. Uses the Pixel ID above; get the access token from Meta Events Manager -> Settings -> Conversions API.', 'fields' => [
                    ['key' => 'facebook_capi_access_token', 'label' => 'Conversion API Access Token', 'type' => 'secret'],
                    ['key' => 'facebook_capi_test_event_code', 'label' => 'Test Event Code (optional — Events Manager \'Test Events\' tab, for verifying setup)', 'type' => 'text'],
                ]],
                ['title' => 'Google', 'enable' => 'google_analytics_enabled', 'fields' => [
                    ['key' => 'google_analytics_id', 'label' => 'Google Analytics ID (G-XXXX)', 'type' => 'text'],
                    ['key' => 'google_tag_manager_id', 'label' => 'Google Tag Manager ID (GTM-XXXX)', 'type' => 'text'],
                ]],
                ['title' => 'TikTok', 'enable' => 'tiktok_enabled', 'fields' => [
                    ['key' => 'tiktok_pixel_id', 'label' => 'TikTok Pixel ID', 'type' => 'text'],
                ]],
            ]],
            'courier' => ['title' => 'Courier API Setup', 'group' => 'courier', 'sections' => [
                ['title' => 'PATHAO', 'enable' => 'pathao_enabled', 'desc' => 'Sandbox: https://courier-api-sandbox.pathao.com — Live: https://api-hermes.pathao.com. Once enabled, assigning this courier with no tracking number auto-creates the Pathao order.', 'fields' => [
                    ['key' => 'pathao_base_url', 'label' => 'Pathao API Base URL', 'type' => 'text'],
                    ['key' => 'pathao_store_id', 'label' => 'Store ID', 'type' => 'text'],
                    ['key' => 'pathao_client_id', 'label' => 'Client ID', 'type' => 'text'],
                    ['key' => 'pathao_client_secret', 'label' => 'Client Secret', 'type' => 'secret'],
                    ['key' => 'pathao_client_email', 'label' => 'Client Email', 'type' => 'text'],
                    ['key' => 'pathao_client_password', 'label' => 'Client Password', 'type' => 'secret'],
                    ['key' => 'pathao_webhook_secret', 'label' => 'Webhook secret (paste into Pathao\'s webhook URL as ?secret=...)', 'type' => 'secret'],
                ]],
                ['title' => 'STEADFAST', 'enable' => 'steadfast_enabled', 'desc' => 'Default API host: https://portal.packzy.com/api/v1. Once enabled, assigning this courier with no tracking number auto-creates the Steadfast order.', 'fields' => [
                    ['key' => 'steadfast_base_url', 'label' => 'API Base URL', 'type' => 'text'],
                    ['key' => 'steadfast_api_key', 'label' => 'API Key', 'type' => 'secret'],
                    ['key' => 'steadfast_secret_key', 'label' => 'Secret Key', 'type' => 'secret'],
                    ['key' => 'steadfast_webhook_secret', 'label' => 'Webhook secret (paste into Steadfast\'s webhook URL as ?secret=...)', 'type' => 'secret'],
                ]],
                ['title' => 'REDX', 'enable' => 'redx_enabled', 'fields' => [
                    ['key' => 'redx_base_url', 'label' => 'API Base URL', 'type' => 'text'],
                    ['key' => 'redx_store_id', 'label' => 'Store ID', 'type' => 'text'],
                    ['key' => 'redx_api_token', 'label' => 'API Token', 'type' => 'secret'],
                ]],
                ['title' => 'PAPERFLY', 'enable' => 'paperfly_enabled', 'fields' => [
                    ['key' => 'paperfly_base_url', 'label' => 'API Base URL', 'type' => 'text'],
                    ['key' => 'paperfly_username', 'label' => 'Username', 'type' => 'text'],
                    ['key' => 'paperfly_password', 'label' => 'Password', 'type' => 'secret'],
                    ['key' => 'paperfly_api_key', 'label' => 'API Key', 'type' => 'secret'],
                ]],
                ['title' => 'Carry Bee', 'enable' => 'carrybee_enabled', 'fields' => [
                    ['key' => 'carrybee_base_url', 'label' => 'Carry Bee Base URL', 'type' => 'text'],
                    ['key' => 'carrybee_client_id', 'label' => 'Carrybee Client Id', 'type' => 'text'],
                    ['key' => 'carrybee_client_secret', 'label' => 'Carrybee Client Secret', 'type' => 'secret'],
                    ['key' => 'carrybee_client_context', 'label' => 'Carrybee Client Context', 'type' => 'text'],
                ]],
            ]],
            'payment' => ['title' => 'Payment Gateway', 'group' => 'payment', 'sections' => [
                ['title' => 'SSLCommerz (Card)', 'enable' => 'card_enabled', 'fields' => [
                    ['key' => 'sslcommerz_store_id', 'label' => 'Store ID', 'type' => 'text'],
                    ['key' => 'sslcommerz_store_password', 'label' => 'Store Password', 'type' => 'secret'],
                    ['key' => 'sslcommerz_sandbox', 'label' => 'Sandbox / test mode', 'type' => 'checkbox'],
                ]],
                ['title' => 'bKash', 'enable' => 'bkash_enabled', 'fields' => [
                    ['key' => 'bkash_app_key', 'label' => 'App Key', 'type' => 'text'],
                    ['key' => 'bkash_app_secret', 'label' => 'App Secret', 'type' => 'secret'],
                    ['key' => 'bkash_username', 'label' => 'Username', 'type' => 'text'],
                    ['key' => 'bkash_password', 'label' => 'Password', 'type' => 'secret'],
                    ['key' => 'bkash_sandbox', 'label' => 'Sandbox / test mode', 'type' => 'checkbox'],
                ]],
                ['title' => 'Nagad', 'enable' => 'nagad_enabled', 'fields' => [
                    ['key' => 'nagad_merchant_id', 'label' => 'Merchant ID', 'type' => 'text'],
                    ['key' => 'nagad_merchant_number', 'label' => 'Merchant Number', 'type' => 'text'],
                    ['key' => 'nagad_public_key', 'label' => 'PG Public Key', 'type' => 'secret'],
                    ['key' => 'nagad_private_key', 'label' => 'Merchant Private Key', 'type' => 'secret'],
                    ['key' => 'nagad_sandbox', 'label' => 'Sandbox / test mode', 'type' => 'checkbox'],
                ]],
                ['title' => 'aamarPay', 'enable' => 'aamarpay_enabled', 'fields' => [
                    ['key' => 'aamarpay_store_id', 'label' => 'Store ID', 'type' => 'text'],
                    ['key' => 'aamarpay_signature_key', 'label' => 'Signature Key', 'type' => 'secret'],
                    ['key' => 'aamarpay_sandbox', 'label' => 'Sandbox / test mode', 'type' => 'checkbox'],
                ]],
            ]],
            'sms' => ['title' => 'SMS Gateway', 'group' => 'sms', 'sections' => [
                ['title' => 'SMS Provider', 'enable' => 'sms_enabled', 'desc' => 'Choose your provider and enter its API key. "Test (log only)" records messages in the log without sending — good for trying it out first.', 'fields' => [
                    ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'options' => ['log' => 'Test (log only)', 'alpha' => 'Alpha SMS (sms.net.bd)', 'mim' => 'MiMSMS', 'bdbulk' => 'BD Bulk SMS (bdbulksms.com)']],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'secret'],
                    ['key' => 'sender_id', 'label' => 'Sender ID / Masking name', 'type' => 'text'],
                ]],
                ['title' => 'Order confirmation SMS', 'enable' => 'order_confirm_enabled', 'desc' => 'When ON, every submitted order sends the customer an SMS with the order number. Placeholders: {name} {order} {total} {store}.', 'fields' => [
                    ['key' => 'order_confirm_template', 'label' => 'Message template', 'type' => 'textarea'],
                ]],
            ]],
            'email' => ['title' => 'Email (SMTP)', 'group' => 'mail', 'sections' => [
                ['title' => 'SMTP server', 'enable' => 'smtp_enabled', 'fields' => [
                    ['key' => 'mail_host', 'label' => 'SMTP Host', 'type' => 'text'],
                    ['key' => 'mail_port', 'label' => 'Port', 'type' => 'text'],
                    ['key' => 'mail_username', 'label' => 'Username', 'type' => 'text'],
                    ['key' => 'mail_password', 'label' => 'Password', 'type' => 'secret'],
                    ['key' => 'mail_encryption', 'label' => 'Encryption (tls / ssl)', 'type' => 'text'],
                    ['key' => 'mail_from_address', 'label' => 'From Address', 'type' => 'text'],
                    ['key' => 'mail_from_name', 'label' => 'From Name', 'type' => 'text'],
                ]],
            ]],
            'google' => ['title' => 'Google & reCAPTCHA', 'group' => 'google_api', 'sections' => [
                ['title' => 'Google Maps', 'enable' => 'google_maps_enabled', 'fields' => [
                    ['key' => 'google_maps_api_key', 'label' => 'Google Maps API Key', 'type' => 'secret'],
                ]],
                ['title' => 'reCAPTCHA', 'enable' => 'recaptcha_enabled', 'fields' => [
                    ['key' => 'recaptcha_site_key', 'label' => 'Site Key', 'type' => 'text'],
                    ['key' => 'recaptcha_secret_key', 'label' => 'Secret Key', 'type' => 'secret'],
                ]],
            ]],
            'verification' => ['title' => 'Order Verification Call', 'group' => 'verification', 'sections' => [
                ['title' => 'Auto-call verification', 'enable' => 'autocall_enabled', 'desc' => 'When enabled, every submitted order automatically triggers a verification call to the customer via your auto-call / IVR provider.', 'fields' => [
                    ['key' => 'autocall_api_url', 'label' => 'Auto-call API URL (endpoint)', 'type' => 'text'],
                    ['key' => 'autocall_api_key', 'label' => 'API Key / Token', 'type' => 'secret'],
                    ['key' => 'autocall_caller_id', 'label' => 'Caller ID / Masking number', 'type' => 'text'],
                    ['key' => 'autocall_retry', 'label' => 'Retry attempts', 'type' => 'number'],
                ]],
            ]],
            'social' => ['title' => 'Socialite Login Credentials', 'group' => 'social', 'sections' => [
                ['title' => 'Facebook', 'enable' => 'facebook_login_enabled', 'fields' => [
                    ['key' => 'facebook_app_id', 'label' => 'Facebook App ID', 'type' => 'text'],
                    ['key' => 'facebook_app_secret', 'label' => 'Facebook App Secret', 'type' => 'secret'],
                    ['key' => 'facebook_redirect', 'label' => 'Facebook Redirect', 'type' => 'text'],
                ]],
                ['title' => 'Google', 'enable' => 'google_login_enabled', 'fields' => [
                    ['key' => 'google_client_id', 'label' => 'Google Client ID', 'type' => 'text'],
                    ['key' => 'google_client_secret', 'label' => 'Google Client Secret', 'type' => 'secret'],
                    ['key' => 'google_redirect', 'label' => 'Google Redirect', 'type' => 'text'],
                ]],
            ]],
            'invoice' => ['title' => 'Invoice Address', 'group' => 'invoice', 'sections' => [
                ['title' => 'Invoice Address Details', 'fields' => [
                    ['key' => 'invoice_address', 'label' => 'Invoice address (shown on invoices)', 'type' => 'textarea'],
                ]],
            ]],
            'delivery' => ['title' => 'Delivery Charge', 'group' => 'delivery', 'sections' => [
                ['title' => 'Zone charges', 'fields' => [
                    ['key' => 'inside_dhaka_charge', 'label' => 'Dhaka Inside charge (৳)', 'type' => 'number'],
                    ['key' => 'suburban_charge', 'label' => 'Dhaka Sub-City charge (৳)', 'type' => 'number'],
                    ['key' => 'outside_dhaka_charge', 'label' => 'Dhaka Outside charge (৳)', 'type' => 'number'],
                ]],
                ['title' => 'Free delivery', 'fields' => [
                    ['key' => 'free_delivery_threshold', 'label' => 'Free delivery over (৳)', 'type' => 'number'],
                    ['key' => 'free_delivery_all_orders', 'label' => 'Free delivery for ALL orders', 'type' => 'checkbox'],
                ]],
            ]],
            'order' => ['title' => 'Order Number', 'group' => 'order', 'sections' => [
                ['title' => 'Order number format', 'desc' => 'How new order numbers are generated. Existing orders keep their numbers.', 'fields' => [
                    ['key' => 'prefix', 'label' => 'Prefix', 'type' => 'text', 'default' => 'ZC'],
                    ['key' => 'format', 'label' => 'Format', 'type' => 'select', 'default' => 'date_random', 'options' => [
                        'date_random' => 'Prefix + Date + Random   (e.g. ZC-20260802-A1B2C3)',
                        'sequential' => 'Prefix + Sequential number   (e.g. ZC-001024)',
                        'random' => 'Prefix + Random   (e.g. ZC-A1B2C3D4)',
                    ]],
                ]],
                ['title' => 'Sequential numbering', 'desc' => 'Only used when the format is "Sequential number". Orders then run e.g. ZC-1000, ZC-1001, ZC-1002…', 'fields' => [
                    ['key' => 'start', 'label' => 'Start order numbers from', 'type' => 'number', 'default' => 1000],
                ]],
            ]],
            'protection' => ['title' => 'Content Protection', 'group' => 'general', 'sections' => [
                ['title' => 'Anti-copy protection', 'enable' => 'public_anti_copy_enabled',
                    'desc' => 'Deters casual copying/inspection on the storefront. Note: this cannot fully stop a determined person — a browser always downloads the front-end code, so it is a deterrent, not real protection. Your backend (PHP) code is never exposed either way.',
                    'fields' => [
                        ['key' => 'public_disable_right_click', 'label' => 'Disable right-click menu', 'type' => 'checkbox'],
                        ['key' => 'public_disable_text_selection', 'label' => 'Disable text selection & copy', 'type' => 'checkbox'],
                        ['key' => 'public_disable_copy_shortcuts', 'label' => 'Block copy shortcuts (Ctrl/Cmd + C, X, S, A)', 'type' => 'checkbox'],
                        ['key' => 'public_disable_devtool_shortcuts', 'label' => 'Block DevTools shortcuts (F12, Ctrl+Shift+I/J/C, Ctrl+U)', 'type' => 'checkbox'],
                    ]],
            ]],
        ];
    }
}
