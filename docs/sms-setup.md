# SMS Setup

Zenna Craft sends SMS (customer OTP login, and — once queued — recovery/campaign/automation messages) through a driver-based system. Switching providers is an `.env` change only; no code changes are needed.

## How it works

`config('sms.driver')` (env `SMS_DRIVER`) selects one of three drivers:

| Driver | What it does |
|---|---|
| `log` (default) | Writes the phone number and full message (including OTP codes) to the app log. Sends nothing. Used in `local`/`testing`. |
| `alpha` | Real send via Alpha SMS (`alpha.net.bd`). |
| `mim` | Real send via MiMSMS. |

`App\Modules\Communication\Services\Channels\SmsChannel` (the existing communication channel — untouched architecturally) delegates to whichever driver `SmsDriverManager` resolves. `CommunicationService` / `SendCommunicationJob` are unchanged; this only replaces what happens inside `SmsChannel::send()`.

Full API specs (endpoints, params, response shapes, sources) are in [`docs/sms-providers.md`](sms-providers.md).

## Env keys

```env
SMS_DRIVER=log            # log | alpha | mim
SMS_API_KEY=               # alpha and mim
SMS_SENDER_ID=              # optional — only sent if SMS_MASKING_ENABLED=true
SMS_MASKING_ENABLED=false   # true = send SMS_SENDER_ID as the masked/branded sender
SMS_MIM_USERNAME=           # mim only — MiMSMS requires a username in addition to the api key
```

Also required for SMS to be attempted at all: the **Studio → Settings → Communication → SMS** toggle (`communication.sms_enabled`) must be on. It defaults to on for new installs; if OTP requests are failing with "Unable to send a verification code right now", check this toggle first.

## Switching drivers

1. Set `SMS_DRIVER=alpha` (or `mim`) in `.env`.
2. Set `SMS_API_KEY` (and `SMS_MIM_USERNAME` if using `mim`).
3. If you have an approved masked sender: set `SMS_SENDER_ID` and `SMS_MASKING_ENABLED=true`. Otherwise leave `SMS_MASKING_ENABLED=false` — both drivers send from the account's default/non-masked sender in that case, which works without any provider-side business-document approval.
4. `php artisan config:clear` if config is cached.

No other change is needed — `SmsChannel` reads the driver at send time.

## Running a real delivery test

Use this to A/B test a provider before committing to it. Do this from a non-production environment against a real BD number you control.

```
php artisan tinker
```
```php
$driver = app(\App\Modules\Communication\Services\Sms\SmsDriverManager::class)->driver();
$result = $driver->send('01XXXXXXXXX', 'Zenna Craft delivery test '.now());
$result->sent;              // bool
$result->message;           // human-readable result
$result->providerMessageId; // provider's message id, if returned
```

To test the full OTP path (not just the driver in isolation), set `SMS_DRIVER` to the provider under test and go through `/customer/login` on the storefront with a real phone number, then check `communication_messages` (status/provider_response) and `communication_logs` in Studio, or directly:

```php
\App\Modules\Communication\Models\CommunicationMessage::where('channel', 'sms')->latest()->first();
```

**What to record per provider, per attempt:**
- Time requested → time actually received on the handset (delivery latency)
- Operator (GP / Robi / Banglalink / Airtel / Teletalk) — delivery rates can differ by operator
- Success/failure per the driver's own result, and whether that matched what was actually received
- Any masking-related rejection if testing with `SMS_MASKING_ENABLED=true` before approval is granted

Repeat with the other driver and compare failure rate and latency per operator before deciding.

## Adding a new provider

1. Add a class implementing `App\Modules\Communication\Contracts\SmsDriver` under `app/Modules/Communication/Services/Sms/Drivers/`.
2. Register it in `config/sms.php` under `drivers.<name>.class`, plus whatever config keys it needs (mirror the `alpha`/`mim` entries).
3. Add the driver's env keys to `.env.example`.
4. Document its API spec in `docs/sms-providers.md` before implementing — cite the source, don't guess parameter names.
