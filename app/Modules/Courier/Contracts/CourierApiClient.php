<?php

namespace App\Modules\Courier\Contracts;

use App\Modules\Courier\Exceptions\CourierApiException;
use App\Modules\Courier\Models\Shipment;

interface CourierApiClient
{
    /** The CourierProvider slug this client handles, e.g. "pathao". */
    public function slug(): string;

    /** True once every credential this client needs has been saved in Settings. */
    public function isConfigured(): bool;

    /**
     * Push the shipment's order to the courier as a new parcel.
     *
     * @return array{tracking_number: string, consignment_id: string, raw: array}
     *
     * @throws CourierApiException
     */
    public function createOrder(Shipment $shipment): array;

    /**
     * Fetch the courier's current status for an already-pushed shipment.
     *
     * @return array{status: ?string, raw: array} status is one of
     *         CourierService::SHIPMENT_STATUSES, or null when the
     *         provider's status couldn't be mapped to one of ours.
     *
     * @throws CourierApiException
     */
    public function trackOrder(Shipment $shipment): array;
}
