<?php

return [
    // Number of days FraudEvent rows are retained before the
    // fraud-event-retention-cleanup schedule (routes/console.php) deletes them.
    'fraud_event_retention_days' => env('FRAUD_EVENT_RETENTION_DAYS', 90),
];
