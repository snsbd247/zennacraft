<?php

namespace App\Modules\Courier\Http\Controllers;

use App\Modules\Courier\Models\Shipment;
use App\Modules\Courier\Services\Api\PathaoCourierClient;
use App\Modules\Courier\Services\Api\SteadfastCourierClient;
use App\Modules\Courier\Services\CourierService;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Real-time status pushes from the couriers themselves. Neither provider's
 * webhook has a documented shared-secret header (see
 * docs/courier-payment-providers.md), so both routes are verified with a
 * secret embedded in the URL's own query string instead — put the full
 * URL (with ?secret=...) into the courier's panel, nothing else to
 * configure on their side.
 */
class CourierWebhookController extends Controller
{
    public function __construct(
        private CourierService $courierService,
        private SettingService $settings,
        private SteadfastCourierClient $steadfastClient,
        private PathaoCourierClient $pathaoClient,
    ) {}

    public function steadfast(Request $request): JsonResponse
    {
        if (! $this->secretValid($request, 'steadfast_webhook_secret')) {
            abort(403);
        }

        $consignmentId = (string) $request->input('consignment_id', '');
        $status = (string) ($request->input('status') ?? $request->input('delivery_status') ?? '');

        return $this->applyStatus('steadfast', $consignmentId, $this->steadfastClient->mapStatus($status));
    }

    public function pathao(Request $request): JsonResponse
    {
        if (! $this->secretValid($request, 'pathao_webhook_secret')) {
            abort(403);
        }

        $consignmentId = (string) $request->input('consignment_id', '');
        $slug = (string) ($request->input('order_status_slug') ?? $request->input('order_status') ?? '');

        return $this->applyStatus('pathao', $consignmentId, $this->mapPathaoStatus($slug));
    }

    protected function applyStatus(string $providerSlug, string $consignmentId, ?string $mappedStatus): JsonResponse
    {
        if ($consignmentId === '') {
            return response()->json(['success' => false, 'message' => 'Missing consignment_id.'], 422);
        }

        $shipment = Shipment::query()
            ->whereHas('courierProvider', fn ($query) => $query->where('slug', $providerSlug))
            ->where('consignment_id', $consignmentId)
            ->first();

        if (! $shipment) {
            // Not an error from the provider's point of view — just nothing
            // for us to update (e.g. a sandbox test ping).
            return response()->json(['success' => true, 'message' => 'No matching shipment.']);
        }

        if ($mappedStatus === null || $mappedStatus === $shipment->status) {
            return response()->json(['success' => true, 'message' => 'No status change.']);
        }

        try {
            $this->courierService->updateShipment($shipment, ['status' => $mappedStatus]);
        } catch (Throwable $exception) {
            logger()->warning('Courier webhook status update failed', [
                'provider' => $providerSlug,
                'consignment_id' => $consignmentId,
                'mapped_status' => $mappedStatus,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Could not apply status.'], 500);
        }

        return response()->json(['success' => true]);
    }

    protected function mapPathaoStatus(string $slug): ?string
    {
        $slug = strtolower($slug);

        return match (true) {
            str_contains($slug, 'delivered') => 'delivered',
            str_contains($slug, 'return') => 'returned',
            str_contains($slug, 'cancel') => 'cancelled',
            str_contains($slug, 'fail') => 'failed',
            str_contains($slug, 'transit'), str_contains($slug, 'pickup') => 'assigned',
            default => null,
        };
    }

    protected function secretValid(Request $request, string $settingKey): bool
    {
        // 'secret' type fields (see ConfigurationController::pages()) are
        // saved via setEncrypted(), so they must be read back the same way.
        $configured = (string) $this->settings->getEncrypted('courier', $settingKey, '');

        return $configured !== '' && hash_equals($configured, (string) $request->query('secret', ''));
    }
}
