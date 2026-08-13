<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerPersonalizationService;
use App\Modules\Order\Models\Order;
use App\Modules\Review\Services\ProductReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CustomerDashboardController extends Controller
{
    public function __construct(
        private CustomerPersonalizationService $personalizationService,
        private ProductReviewService $reviewService,
    ) {}

    public function dashboard(Request $request): View|RedirectResponse
    {
        $customer = $this->currentCustomer();

        if (! $customer) {
            return $this->redirectToLogin();
        }

        $customer->load([
            'shadowAccount',
            'couponUsages.coupon',
            'communicationMessages' => fn ($query) => $query->latest()->limit(5),
        ]);

        $recentOrders = $customer->orders()
            ->with(['items', 'shipment.courierProvider', 'coupon'])
            ->latest()
            ->limit(5)
            ->get();

        return view('storefront.customer.dashboard', [
            'customer' => $customer,
            'recentOrders' => $recentOrders,
            'orderStats' => $this->orderStats($customer),
            'journey' => $this->journey($customer, $recentOrders),
            'personalization' => $this->personalizationService->customerDashboard($customer, $request),
            'loyaltyProfile' => $this->reviewService->loyaltyProfile($customer),
            'currentCartCount' => app(\App\Modules\Checkout\Services\CartService::class)->count(),
        ]);
    }

    public function orders(): View|RedirectResponse
    {
        $customer = $this->currentCustomer();

        if (! $customer) {
            return $this->redirectToLogin();
        }

        return view('storefront.customer.orders.index', [
            'customer' => $customer,
            'orders' => $customer->orders()
                ->with(['items', 'shipment.courierProvider', 'coupon'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function showOrder(Order $order): View|RedirectResponse
    {
        $customer = $this->currentCustomer();

        if (! $customer) {
            return $this->redirectToLogin();
        }

        if ((int) $order->customer_id !== (int) $customer->id) {
            abort(404);
        }

        return view('storefront.customer.orders.show', [
            'customer' => $customer,
            'order' => $order->load([
                'items.product.thumbnail',
                'coupon',
                'shipment.courierProvider',
                'trackingEvents',
                'statusHistories',
                'verificationAttempts',
                'communicationMessages',
                'customerCommunications',
                'productReviews.product',
                'productReviews.variant',
            ]),
            'invoiceUrl' => $this->invoiceUrl($order),
            'reviewableItems' => $this->reviewService->reviewableOrderItems($customer, $order),
            'orderReviews' => $order->productReviews()->with(['product:id,name', 'variant:id,name'])->latest()->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $customer = $this->currentCustomer();

        if (! $customer) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please log in again.'], 401);
            }

            return $this->redirectToLogin();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->fill($validated)->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Profile updated.',
                'customer' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'address' => $customer->address,
                ],
            ]);
        }

        return redirect()
            ->route('customer.dashboard')
            ->with('success', 'Profile updated.');
    }

    protected function currentCustomer(): ?Customer
    {
        $customerId = session('customer_id');

        if (! $customerId) {
            return null;
        }

        return Customer::find($customerId);
    }

    protected function redirectToLogin(): RedirectResponse
    {
        return redirect()
            ->route('customer.login')
            ->with('status', 'Please log in to access your account.');
    }

    protected function invoiceUrl(Order $order): string
    {
        return URL::signedRoute('checkout.invoice', ['order' => $order->order_number]);
    }

    protected function orderStats(Customer $customer): array
    {
        return [
            'total' => (int) $customer->orders()->count(),
            'pending' => (int) $customer->orders()->where('status', 'pending')->count(),
            'delivered' => (int) $customer->orders()->where('status', 'delivered')->count(),
            'cancelled' => (int) $customer->orders()->where('status', 'cancelled')->count(),
            'returned' => (int) $customer->orders()->where('status', 'returned')->count(),
            'spent' => (float) $customer->orders()->whereNotIn('status', ['cancelled', 'returned'])->sum('total'),
            'coupon_savings' => (float) $customer->couponUsages()->sum('discount_amount'),
        ];
    }

    protected function journey(Customer $customer, $recentOrders): array
    {
        $latestOrder = $recentOrders->first();
        $latestNotes = (string) ($latestOrder?->notes ?? '');

        return [
            'customer_since' => $customer->created_at,
            'last_order_at' => $customer->last_order_at ?: $latestOrder?->created_at,
            'latest_order' => $latestOrder,
            'latest_invoice_url' => $latestOrder ? $this->invoiceUrl($latestOrder) : null,
            'cart_count' => app(\App\Modules\Checkout\Services\CartService::class)->count(),
            'coupon_count' => (int) $customer->couponUsages()->count(),
            'communication_count' => (int) ($customer->communicationMessages()->count() + $customer->communications()->count()),
            'alternative_phone' => $this->noteValue($latestNotes, 'Alternative phone'),
            'area' => $this->noteValue($latestNotes, 'Area'),
            'district' => $this->noteValue($latestNotes, 'District'),
        ];
    }

    protected function noteValue(string $notes, string $label): ?string
    {
        if ($notes === '') {
            return null;
        }

        if (preg_match('/^'.preg_quote($label, '/').':\s*(.+)$/mi', $notes, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
