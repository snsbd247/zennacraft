<?php

return [
    // Actual queue names the worker(s) listen on. Change these here (or via
    // env) if the queue names ever need to change — nothing else in the
    // codebase should hardcode 'otp'/'transactional'/'bulk' as a literal
    // queue name; always resolve through this file.
    //
    // Tier meaning:
    //   otp           - login codes. Must be picked up within seconds.
    //                   Never anything else — see CommunicationMessage::
    //                   queueTier().
    //   transactional - single-customer, event-triggered messages (order
    //                   status changes, verification outcomes, review
    //                   requests, recovery reminders). Not urgent enough to
    //                   need their own worker, but must not queue behind a
    //                   multi-thousand-recipient campaign.
    //   bulk          - campaign/segment/coupon blasts that fan out to many
    //                   customers in one call.
    'otp' => env('QUEUE_NAME_OTP', 'otp'),
    'transactional' => env('QUEUE_NAME_TRANSACTIONAL', 'transactional'),
    'bulk' => env('QUEUE_NAME_BULK', 'bulk'),
];
