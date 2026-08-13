<?php

return [
    // Base URL of your central License + Update panel. EMPTY = licensing OFF:
    // the app runs with full access and NOTHING phones home. Set this only once
    // your panel is live, e.g. https://panel.yourbrand.com
    'server' => env('LICENSE_SERVER_URL', ''),

    // Which product this installation is, in the panel's catalogue. Every future
    // tool you build sets its own slug here and registers under the same panel.
    'product' => env('LICENSE_PRODUCT', 'zenna-craft'),

    // The version this codebase currently is. The updater compares this against
    // the panel's latest published release to decide whether an update exists.
    // Bump it in each release you publish.
    'version' => env('APP_VERSION', '1.0.0'),

    // If the panel is unreachable, keep trusting the last good validation for
    // this many days before treating the licence as lapsed — so a network blip
    // or a brief panel outage never breaks a live store.
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 7),

    // Public key used to verify the digital signature on downloaded updates
    // (the panel signs each release with the matching private key). Wired up
    // when the one-click installer ships in the panel phase.
    'public_key' => env('LICENSE_PUBLIC_KEY', ''),
];
