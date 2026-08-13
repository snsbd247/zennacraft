<?php

namespace App\Modules\Checkout\Exceptions;

use RuntimeException;

/** Thrown by a payment gateway client when a create/execute/query call can't be completed. */
class PaymentGatewayException extends RuntimeException {}
