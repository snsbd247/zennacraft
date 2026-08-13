<?php

namespace App\Modules\Courier\Exceptions;

use RuntimeException;

/**
 * Thrown by a CourierApiClient when a remote push/track call can't be
 * completed — bad/missing credentials, a rejected order, an unmatched
 * city/zone, or a network failure. Always carries a message safe to show
 * a staff member verbatim (see docs/courier-payment-providers.md).
 */
class CourierApiException extends RuntimeException {}
