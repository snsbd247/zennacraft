<?php

namespace App\Modules\Shared\Support;

use App\Modules\License\Services\LicenseService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Confirms this installation is in good standing before a core mutation
 * (an order status change, a new order) is allowed to proceed — the same
 * check EnsureLicenseIsValid applies at the HTTP layer, called again here
 * so removing that one middleware file doesn't, by itself, unlock the app.
 * Deliberately not named/grouped with the License module: see that
 * middleware and app/Modules/License/Services/LicenseService for the full
 * license-verification system this enforces.
 */
class OperationalStanding
{
    /** @throws HttpException when the installation is not in good standing */
    public static function assertActive(): void
    {
        $result = app(LicenseService::class)->getEffectiveStatus();

        if ($result['blocked']) {
            throw new HttpException(
                423,
                $result['message'] ?: 'This installation is not currently active.',
                null,
                ['X-Standing-Code' => 'ACCOUNT_HOLD']
            );
        }
    }
}
