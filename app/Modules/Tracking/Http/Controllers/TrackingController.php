<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function __construct(private TrackingService $trackingService) {}

    public function publicForm(Request $request): View
    {
        $number = trim((string) $request->query('order', ''));
        $order = $number !== '' ? $this->trackingService->publicLookupByNumber($number) : null;

        return view('storefront.tracking.form', [
            'order' => $order,
            'searched' => $number,
            'notFound' => $number !== '' && ! $order,
        ]);
    }

    public function publicLookup(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string'],
            'phone' => ['required', 'string'],
        ]);

        $order = $this->trackingService->publicLookup($validated['order_number'], $validated['phone']);

        if (! $order) {
            return back()
                ->withErrors(['order_number' => 'No matching order was found for that order number and phone.'])
                ->withInput();
        }

        return view('storefront.tracking.result', [
            'order' => $order,
            'shipment' => $order->shipment,
            'timeline' => $this->trackingService->timelineForOrder($order),
        ]);
    }

    public function customerOrderTracking(Order $order): View|RedirectResponse
    {
        $customer = $this->currentCustomer();

        if (! $customer) {
            return redirect()
                ->route('customer.login')
                ->with('status', 'Please log in to access your account.');
        }

        if ((int) $order->customer_id !== (int) $customer->id) {
            abort(404);
        }

        // Load what the premium tracking view needs (same as the main
        // tracking page): line items with their product thumbnails.
        $order->load(['items.product.thumbnail']);

        return view('storefront.customer.orders.tracking', [
            'customer' => $customer,
            'order' => $order,
            'invoiceUrl' => URL::signedRoute('checkout.invoice', ['order' => $order->order_number]),
        ]);
    }

    protected function currentCustomer(): ?Customer
    {
        $customerId = session('customer_id');

        if (! $customerId) {
            return null;
        }

        return Customer::find($customerId);
    }
}
