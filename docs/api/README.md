# Zenna Craft API Foundation

## Base URL

All version 1 endpoints use:

```text
/api/v1
```

## Response Format

Success:

```json
{
  "success": true,
  "message": "Request successful",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Something went wrong",
  "errors": {}
}
```

Paginated:

```json
{
  "success": true,
  "message": "Request successful",
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

## Customer Auth

```text
POST /api/v1/auth/customer/request-otp
POST /api/v1/auth/customer/verify-otp
POST /api/v1/auth/customer/logout
GET  /api/v1/auth/customer/me
```

Customer OTP uses the existing OTP service. In local/debug mode only, the generated OTP may be returned for development. Production responses do not include the OTP code.

Authenticated customer endpoints require:

```text
Authorization: Bearer {token}
```

## Staff Auth

```text
POST /api/v1/auth/staff/login
POST /api/v1/auth/staff/logout
GET  /api/v1/auth/staff/me
```

Only active staff can login. Staff profile responses include role slugs and permission slugs, but never passwords or remember tokens.

## Categories

```text
GET /api/v1/categories
GET /api/v1/categories/{category}
```

The `{category}` value may be a slug or numeric ID.

Query parameters:

```text
search
per_page
```

Only active categories are returned.

## Products

```text
GET /api/v1/products
GET /api/v1/products/{product}
```

The `{product}` value may be a slug or numeric ID.

Query parameters:

```text
search
category
per_page
```

Only active products are returned. Product detail includes category, thumbnail, gallery media, and active variants when available.

## Customer Orders

```text
GET /api/v1/customer/orders
GET /api/v1/customer/orders/{order}
```

Customer order endpoints require a customer Sanctum token. Customers can only access their own orders.

## Tracking

```text
POST /api/v1/tracking/lookup
```

Input:

```text
order_number
phone
```

The lookup requires both order number and matching customer phone. Public tracking responses do not expose customer email, address, or internal notes.

## Rate Limits

Configured rate limiters:

```text
api-public: 60 requests/minute
api-customer: 120 requests/minute
api-staff: 300 requests/minute
```

## Error Handling

API validation, unauthenticated, unauthorized, and not-found responses are returned as JSON using the shared API error shape.
