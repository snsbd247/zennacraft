<?php

namespace App\Modules\Courier\Http\Controllers;

use App\Modules\Courier\Exceptions\CourierApiException;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Courier\Services\CourierService;
use App\Modules\Order\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourierController extends Controller
{
    public function __construct(private CourierService $courierService) {}

    /**
     * "Courier" — provider overview + the recent shipment list, plus an
     * assign-to-courier form for orders that aren't shipped yet.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'courier_provider_id', 'tracking_number', 'order_number']);

        $providers = CourierProvider::query()
            ->withCount('shipments')
            ->orderBy('name')
            ->get();

        $shipments = $this->courierService->paginate($filters, 15);

        // Orders that can still be assigned a courier — no shipment yet and
        // not in a terminal state.
        $assignableOrders = Order::query()
            ->whereDoesntHave('shipment')
            ->whereIn('status', ['confirmed', 'processing', 'shipped'])
            ->latest()
            ->limit(50)
            ->get(['id', 'order_number', 'customer_name', 'total', 'status']);

        return view('studio.courier.index', [
            'providers' => $providers,
            'shipments' => $shipments,
            'filters' => $filters,
            'assignableOrders' => $assignableOrders,
            'shipmentStatuses' => $this->courierService->shipmentStatuses(),
            'activeProviders' => $this->courierService->activeProviders(),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'courier_provider_id' => ['required', 'integer', 'exists:courier_providers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'courier_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        try {
            $shipment = $this->courierService->assignShipment($order, [
                'courier_provider_id' => $validated['courier_provider_id'],
                'tracking_number' => $validated['tracking_number'] ?? null,
                'courier_cost' => $validated['courier_cost'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $providerSlug = $shipment->courierProvider?->slug;
        $providerName = $shipment->courierProvider?->name ?? 'the courier';
        $base = 'Courier assigned to order '.$order->order_number;

        if (! $this->courierService->hasLiveApiClient($providerSlug)) {
            return back()->with('success', $base.'.');
        }

        if (filled($shipment->consignment_id)) {
            return back()->with('success', $base.' and pushed to '.$providerName.' (tracking: '.$shipment->tracking_number.').');
        }

        return back()->with('error', $base.', but the push to '.$providerName.' failed — check the Courier screen to retry or verify the API credentials in Settings.');
    }

    /** "Push to courier" button: send an already-assigned shipment to its provider's live API. */
    public function pushToApi(Shipment $shipment): RedirectResponse
    {
        try {
            $this->courierService->pushToProviderApi($shipment);
        } catch (CourierApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Shipment pushed to the courier API.');
    }

    /** "Sync status" button: pull the provider's current status for this shipment. */
    public function syncStatus(Shipment $shipment): RedirectResponse
    {
        try {
            $this->courierService->syncStatusFromProviderApi($shipment);
        } catch (CourierApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Shipment status synced from the courier.');
    }
}
