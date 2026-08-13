# Courier & Payment Provider API Specs (research notes)

Captured before implementation so this research survives a session/context reset — same reasoning as `docs/sms-providers.md`. Sources are fetched package READMEs/docs (no official PDF/Postman doc was reachable from this session), cross-checked across multiple independent implementations where possible. Ambiguities are flagged, not guessed.

---

## Pathao Courier (Merchant / "Aladdin" API)

**Base URLs**
- Sandbox: `https://courier-api-sandbox.pathao.com`
- Live: `https://api-hermes.pathao.com`
- Credentials (client_id/client_secret/username/password) come from the Pathao Merchant Dashboard's Developer API page, per-environment (sandbox and live are separate accounts).

**Auth — Issue Token**
- `POST /aladdin/api/v1/issue-token`
- Body: `client_id`, `client_secret`, `username` (merchant email), `password`, `grant_type` (`password` for the first token, `refresh_token` to renew).
- Response: `access_token`, `refresh_token`, `expires_in`, `token_type`.
- Confirmed consistently across two independent SDKs (Sifat07/pathao-merchant-sdk, codeboxrcodehub/pathao-courier).

**Create Order**
- `POST /aladdin/api/v1/orders`, `Authorization: Bearer {access_token}`.
- Request fields: `store_id`, `merchant_order_id`, `recipient_name`, `recipient_phone`, `recipient_address`, `recipient_city` (city_id), `recipient_zone` (zone_id), `recipient_area` (area_id, optional), `delivery_type` (48 = normal, 12 = on-demand), `item_type` (1 = document, 2 = parcel), `special_instruction`, `item_quantity`, `item_weight`, `amount_to_collect`, `item_description`.
- Response: `consignment_id`, `merchant_order_id`, `order_status`, `delivery_fee`.

**Location lookups** (needed because `recipient_city`/`recipient_zone` are Pathao's own numeric IDs, not free-text district names)
- `GET /aladdin/api/v1/city-list` → `city_id`, `city_name`
- `GET /aladdin/api/v1/cities/{city_id}/zone-list` → `zone_id`, `zone_name`
- `GET /aladdin/api/v1/zones/{zone_id}/area-list` → `area_id`, `area_name`, `home_delivery_available`, `pickup_available`

**Order tracking**
- `GET /aladdin/api/v1/orders/{consignment_id}/info` → `consignment_id`, `order_status`, `order_status_slug`, `updated_at`, `invoice_id`. Path form is the documented REST convention for this API family; not independently re-verified against a live account in this session — the client treats a non-2xx/unexpected shape as a soft failure rather than crashing.

**Price calculation** — mentioned by multiple SDKs (`store_id`, `item_type`, `delivery_type`, `item_weight`, `recipient_city`, `recipient_zone` → `price`, `discount`, `cod_enabled`, `cod_percentage`) but no source in this session showed the exact path. **Not implemented** — out of scope for this pass; `amount_to_collect` is set directly from the order total instead of a quoted courier price.

**Zone/area auto-detection risk (flagged, not guessed):** our orders store a free-text `district` and `address`, not Pathao's city/zone taxonomy. The client matches `district` to Pathao's city list by exact case-insensitive name (reliable — both use real Bangladesh district names) and then tries to match the order's address text against that city's zone names. If the zone can't be confidently matched, `createOrder()` throws rather than guessing — the existing manual tracking-number entry on the shipment form is the fallback for that order.

---

## Steadfast Courier

**Base URL**: `https://portal.packzy.com/api/v1` (the historical/stable API host — Steadfast's newer portal domain `portal.steadfast.com.bd` fronts the same API in some integrations; `packzy.com` is what's consistently cited and is used as the default here, overridable via Settings).

**Auth**: static headers on every request — `Api-Key: {api_key}`, `Secret-Key: {secret_key}`, `Content-Type: application/json` (from the merchant panel's API access page). No token exchange.

**Create Order**
- `POST /create_order`
- Request: `invoice` (use our `order_number`), `recipient_name`, `recipient_phone`, `recipient_address`, `cod_amount`, `note`.
- Response: `{"status": 200, "message": "...", "consignment": {"consignment_id": 123, "tracking_code": "ABCD1234", "status": "..."}}`. Confirmed verbatim (exact shape) from an independent package's documented example.

**Bulk create**: `POST /create_order/bulk-order` — not implemented (single-order flow only, matching how shipments are assigned one at a time in Studio).

**Status check** — endpoint path names inferred from the consistent method-naming across every Steadfast package found (`status_by_cid`, `status_by_invoice`, `status_by_trackingcode` — this exact naming convention appears independently in half a dozen unrelated open-source clients, which is why it's trusted here despite no raw HTTP example being fetched):
- `GET /status_by_cid/{consignment_id}`
- `GET /status_by_invoice/{invoice}`
- `GET /status_by_trackingcode/{tracking_code}`
- Response: `{"status": 200, "delivery_status": "..."}`. Known `delivery_status` values: `pending`, `delivered`, `partial_delivered`, `cancelled`, `hold`, `in_review`, `unknown`, `unknown_approval`.

**Balance**: `GET /get_balance` → `{"status": 200, "current_balance": 5000.00}`. Not wired into the UI in this pass.

**Webhook** — Steadfast lets a merchant paste a callback URL into their panel; payload fields seen consistently: `notification_type`, `consignment_id`, `invoice`, `cod_amount`, `status`/`delivery_status`, `tracking_code`, `updated_at`. **No documented shared-secret header** was found in any source — this integration verifies the webhook by putting a random secret directly in the URL's query string (`?secret=...`) that only the URL Steadfast is given contains, since we control that URL. Put the full URL (with the secret) into Steadfast's panel; there is nothing to configure on their side beyond the URL itself.

---

## bKash Checkout (URL-based), v1.2.0-beta

This is the plain hosted-checkout flow (grant token → create payment → redirect → callback → execute payment) — **not** the "Tokenized Checkout" agreement/subscription API (which uses `/tokenized/checkout/*` paths and a saved payer agreement for repeat charges without a redirect). A one-off storefront order is exactly what the plain Checkout API is for.

**Base URLs**
- Sandbox: `https://checkout.sandbox.bka.sh/v1.2.0-beta`
- Live: `https://checkout.pay.bka.sh/v1.2.0-beta` (pattern-matched from the sandbox/live host convention documented for bKash's other v1.2.0-beta products — `sandbox.bka.sh` / `pay.bka.sh` — not independently re-confirmed for this exact subdomain in this session; flagged for verification against the merchant onboarding email/dashboard before go-live).

**Grant Token**
- `POST /checkout/token/grant`
- Headers: `username`, `password` (the bKash-issued API username/password, not app_key/secret), `Content-Type: application/json`, `Accept: application/json`.
- Body: `app_key`, `app_secret`.
- Response: `id_token`, `refresh_token`, `token_type`, `expires_in`.

**Create Payment**
- `POST /checkout/payment/create`
- Headers: `Authorization: {id_token}`, `X-APP-Key: {app_key}`, `Content-Type: application/json`.
- Body: `amount`, `currency` (`BDT`), `intent` (`sale`), `merchantInvoiceNumber` (our `order_number`), `callbackURL`.
- Response: `paymentID`, `bkashURL` (redirect the customer's browser here), `successCallbackURL`, `failureCallbackURL`, `cancelledCallbackURL`, `statusCode`, `statusMessage`. Success = `statusCode === '0000'`.

**Redirect back**: bKash appends `paymentID` and `status` (`success` | `failure` | `cancel`) as query params on `callbackURL` — this is why the callback route does **not** use Laravel's `signed` middleware (bKash-appended params would break the signature); instead the callback loads the order by the `paymentID` we stored when creating the payment and treats the query-string `status` as a hint only, never as proof — the actual payment result always comes from Execute Payment below.

**Execute Payment**
- `POST /checkout/payment/execute/{paymentID}` (no body), same auth headers as Create Payment.
- Response: `paymentID`, `trxID`, `transactionStatus`, `amount`, `payerReference`, `customerMsisdn`, `statusCode`, `statusMessage`. Success = `transactionStatus === 'Completed'` and `statusCode === '0000'`.

**Query Payment** — `GET /checkout/payment/status/{paymentID}` (path inferred from the create/execute path pattern, not independently confirmed). Implemented as a best-effort admin "re-check" helper only, never on the critical checkout path.

**Refund** — not implemented in this pass (no source in this session documented the exact request shape with confidence; flagged for a follow-up if refunds are needed).

---

## What's deliberately not built in this pass

- Pathao/bKash refund and cancel-order flows.
- Pathao price-quote-before-assigning (courier cost still comes from the existing manual `courier_cost` field on the shipment).
- Nagad / SSLCommerz / aamarPay — settings fields already exist in Studio → Settings → Payment Gateway, but no client class; same "reveal-slot, not wired" state as before.
- Steadfast/Pathao bulk order creation.

All of the above have their Settings UI already in place (`ConfigurationController::pages()`) and can be wired the same way this pass wires Pathao, Steadfast and bKash, without a schema change.
