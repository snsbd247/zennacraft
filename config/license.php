<?php

return [
    // Central license server (fixed — every installation of this product
    // talks to the same vendor server; not meant to be per-customer
    // configurable, but still overridable via env for staging/testing).
    'server' => env('LICENSE_SERVER_URL', 'https://api.syncsolutionbd.com/api/v1'),

    // This product's slug in the license server's catalogue.
    'product_slug' => env('LICENSE_PRODUCT_SLUG', 'ecommerce1'),

    // RSA-2048 public key used to verify the signature on every
    // activate/verify response. Safe to embed — it can only verify a
    // signature, never produce one. If a response's signature doesn't
    // verify against this key, it is never trusted or stored.
    'public_key_pem' => env('LICENSE_PUBLIC_KEY_PEM', <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwVlQsO1Dy4wp0LoIwdjj
YJrzsBbnj3QvvhoLMo608U68YX5WduNh2dOpSF27+vSMq+vC58G3+YoLtN87qm3h
T6PkY/BlKQDpzzCGPdUJob9k/bQvJRKuK2GOXTfZq3uR5BNQW2pS5ZWBbF7+8+H/
ut6bPHkVlhuxSarW0DirSXNBrsbhb5ar0dlBDPk3Em9FVySOvxv1SiijVfzSuT/4
Fd/inJFuhnrgAhTNUsLEQH9jC9PnA6sGDNfSRUtnrrIscZaVg/kWNgJupsmmIl5i
+cQv2QBv4HTCC1n2AmKOwBMR8Up3t0ue3EVsxhUxqkAN9af4rIG38tAcWQ+ElvK0
yQIDAQAB
-----END PUBLIC KEY-----
PEM),

    // Cache window for verify() — how long a "still valid" result is
    // trusted before the next boot/login triggers a fresh server check.
    'verify_cache_hours' => (int) env('LICENSE_VERIFY_CACHE_HOURS', 6),

    // Grace window after expiry (server-side "grace" status) — informational
    // only, the server is authoritative; used for the local fallback below.
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 2),

    // If the license server can't be reached at all, how long we keep
    // trusting the last signature-verified result before treating the
    // installation as blocked.
    'offline_trust_days' => (int) env('LICENSE_OFFLINE_TRUST_DAYS', 5),
];
