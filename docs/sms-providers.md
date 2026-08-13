# SMS Provider API Specs (research notes)

Captured before implementation so this research survives a session/context reset. These are the two candidate real drivers for Task A5 (`alpha`, `mim`), plus the always-available `log` driver. No vendor has been chosen — this document exists to make switching cheap, not to recommend one.

---

## Alpha SMS (alpha.net.bd / sms.net.bd)

**Source:** `https://www.alpha.net.bd/SMS/api/` (fetched directly), corroborated by `https://sms.net.bd/api`.

### Send SMS
- **Endpoint:** `https://api.sms.net.bd/sendsms`
- **Method:** GET or POST (both supported; driver will use POST)

| Parameter | Required | Notes |
|---|---|---|
| `api_key` | Yes | From the SMS Panel's "API" page |
| `msg` | Yes | Message body |
| `to` | Yes | Recipient number(s); `880` country-code format or local `01X` format; comma-separate for multiple |
| `schedule` | No | `Y-m-d H:i:s` — not used by this driver (OTP/transactional is always immediate) |
| `sender_id` | No | Approved Sender ID for masking. Omit for non-masking/numeric sends. |
| `content_id` | No | Required for bulk SMS only if campaign content is pre-approved — not used for single transactional sends |

**Example request:**
```
POST https://api.sms.net.bd/sendsms?api_key={KEY}&msg={MSG}&to=8801800000000
```

**Success response (HTTP 200, JSON):**
```json
{
    "error": 0,
    "msg": "Request successfully submitted",
    "data": { "request_id": 12345 }
}
```
Success = `error === 0`. `data.request_id` is the provider message id.

**Error responses:** non-zero `error` and/or non-200 HTTP status.

| Code | Meaning |
|---|---|
| 400 | Missing or invalid parameter |
| 405 | Authorization required (bad `api_key`) |
| 410 | Account expired |
| 414 | Message is empty |
| 417 | Insufficient balance |

Other documented endpoints (not used by this phase): Report API `https://api.sms.net.bd/report/request/{id}/`, Balance API `https://api.sms.net.bd/user/balance/`.

### Masking vs non-masking
`sender_id` is what enables a masked/branded sender. Omitting it sends from Alpha SMS's default numeric/shared sender. This maps directly to our `SMS_MASKING_ENABLED` toggle: when off, the driver omits `sender_id` from the request even if `SMS_SENDER_ID` is set in `.env`.

---

## MiMSMS (mimsms.com)

**Source:** official vendor Laravel package, `github.com/mimsms/mim-sms-laravel`, source file `src/MiMSMSManager.php` fetched directly. The public API-docs landing page (`https://www.mimsms.com/api-documentation/`) confirms the product exists but did not expose parameter-level detail through the fetch tool available in this session — the package source is the authoritative source used here.

### Config (per the package's own `.env` convention)
- `MIMSMS_USERNAME` — account username
- `MIMSMS_API_KEY` — API key
- `MIMSMS_BASE_URL` — `https://api.mimsms.com/api/SmsSending`

Every request (GET or POST) has `UserName` and `Apikey` merged into it automatically alongside the endpoint-specific payload.

### Send single SMS
- **Endpoint:** `POST {MIMSMS_BASE_URL}/SMS`
- **Headers:** `Content-Type: application/json`, `Accept: application/json`

| Parameter | Required | Notes |
|---|---|---|
| `MobileNumber` | Yes | Single number, `880` country-code format (e.g. `8801XXXXXXXXX`) |
| `Message` | Yes | Message body |
| `SenderName` | Yes | Masked sender name/ID, **or** the account's default/non-masked sender identifier — same field serves both; see Masking note below |
| `TransactionType` | Yes | `'T'` = transactional, `'P'` = promotional. OTP/order messages use `'T'`. |
| `CampaignId` | No | Not used by this driver |

**Response shape (from the package's own success/error handling logic):**
```json
{ "statusCode": "200", "responseResult": "..." }
```
Success = `statusCode === '200'` (string, not integer, per the package's own comparison — `$responseData['statusCode'] !== '200'` is the failure check). Any other `statusCode`, or an HTTP-level failure (`$response->failed()`), is a failure; `responseResult` carries the human-readable error message on failure.

**Ambiguity flagged, not guessed:** the package source does not show the exact field name MiMSMS uses to return a provider message id on a *successful send* (a `trxnId` field is used as an *input* to the separate delivery-report endpoint, `POST {baseUrl}/DlrApi` with `MobileNumber` + `trxnId`, implying the send response likely contains a `trxnId`, but this isn't directly confirmed in the source available to this session). The `mim` driver therefore reads `trxnId` defensively (`$body['trxnId'] ?? null`) and does not fail or warn if it's absent — the provider message id is a nice-to-have for delivery-report lookups, not required for determining send success, so this doesn't block correctness.

### Masking vs non-masking
MiMSMS does not have a separate masking flag in the send payload — `SenderName` itself is either an approved masked/branded name (requires business/trade-license approval from MiMSMS) or the account's assigned default sender. When `SMS_MASKING_ENABLED=false`, the `mim` driver omits `SenderName` from the request if `SMS_SENDER_ID` is unset, letting MiMSMS fall back to the account default; when a `SMS_SENDER_ID` is configured and masking is enabled, it's sent as `SenderName`.

### Other endpoints (not used by this phase, documented for completeness)
- One-to-many: `POST {baseUrl}/OneToMany` — same fields, `MobileNumber` becomes a comma-separated list.
- Dynamic per-recipient SMS: `POST {baseUrl}/DSMS` — `SenderName`, `TransactionType`, `SmsData` (array of `{MobNumber, Message}`).
- Delivery report: `POST {baseUrl}/DlrApi` — `MobileNumber`, `trxnId`.
- Balance check: `POST {baseUrl}/balanceCheck` — auth params only.

---

## `log` driver (no external vendor)

Not a real provider — writes the phone number and full message body (including the OTP code) to the configured Laravel log channel and returns success. This is the `SMS_DRIVER` default in `local`/`testing` and replaces the old `config('app.debug')` on-screen OTP reveal: to see the code locally, check the log instead of the page.
