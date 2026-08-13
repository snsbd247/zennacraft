<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\Customer360Service;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Services\CustomerFraudService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Studio "Customers" (Customer group): a searchable customer list with a
 * behaviour tag + block state, plus a per-customer profile that pulls the
 * full order history and the storefront activity/add-to-cart trail. Reuses
 * Customer360Service (metrics) and CustomerFraudService (block/unblock via the
 * same blacklist checkout already enforces) — no parallel logic.
 */
class CustomerController extends Controller
{
    public function __construct(
        private Customer360Service $customer360,
        private CustomerFraudService $fraudService,
        private MediaService $media,
    ) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all'); // all | blocked | active
        $perPage = max(10, min(100, (int) $request->query('per_page', 20)));

        $customers = Customer::query()
            ->withCount('orders')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->when($status === 'blocked', fn ($q) => $q->whereIn('id', $this->blockedCustomerIds()))
            ->when($status === 'active', fn ($q) => $q->whereNotIn('id', $this->blockedCustomerIds()))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $ids = $customers->getCollection()->pluck('id')->all();

        $allBlocked = $this->blockedCustomerIds();
        $totalCustomers = (int) Customer::query()->count();

        return view('studio.customers.index', [
            'customers' => $customers,
            'term' => $term,
            'status' => $status,
            'perPage' => $perPage,
            'blockedIds' => $this->blockedCustomerIds($ids),
            'cityByCustomer' => $this->latestOrderCity($ids),
            // Stat strip (global counts).
            'totalCustomers' => $totalCustomers,
            'blockedCount' => count($allBlocked),
            'activeCount' => max(0, $totalCustomers - count($allBlocked)),
            'newCustomers' => (int) Customer::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    public function show(Customer $customer): View
    {
        $orderMetrics = $this->customer360->orderMetrics($customer);
        $financial = $this->customer360->financialMetrics($customer);

        $orders = $customer->orders()->withCount('items')->latest()->limit(50)->get();

        // The products this customer added to their cart — deduped to the
        // distinct products (most recent first), with image / name / SKU / price.
        $cartProducts = CustomerBehaviorEvent::query()
            ->where('customer_id', $customer->id)
            ->whereIn('event_type', ['added_to_cart', 'cart_updated', 'package_selected'])
            ->whereNotNull('product_id')
            ->with('product.thumbnail')
            ->latest('occurred_at')
            ->get()
            ->filter(fn (CustomerBehaviorEvent $e) => $e->product !== null)
            ->unique('product_id')
            ->map(fn (CustomerBehaviorEvent $e) => [
                'product' => $e->product,
                'added_at' => $e->occurred_at,
                'image' => $e->product->thumbnail ? $this->media->url($e->product->thumbnail) : null,
            ])
            ->values();

        return view('studio.customers.show', [
            'customer' => $customer,
            'metrics' => $orderMetrics,
            'financial' => $financial,
            'behaviorTag' => $this->behaviorTag($customer),
            'blocked' => $this->isBlocked($customer),
            'orders' => $orders,
            'cartProducts' => $cartProducts,
        ]);
    }

    public function block(Request $request, Customer $customer): JsonResponse
    {
        $reason = trim((string) $request->input('reason', '')) ?: 'Blocked from Customers list.';
        $this->fraudService->blacklistCustomer($customer, (string) $customer->phone, $reason);

        return response()->json(['message' => 'Customer blocked.', 'blocked' => true]);
    }

    public function unblock(Request $request, Customer $customer): JsonResponse
    {
        CustomerBlacklist::query()
            ->where('active', true)
            ->where(fn ($q) => $q->where('customer_id', $customer->id)->orWhere('phone', $customer->phone))
            ->get()
            ->each(fn (CustomerBlacklist $b) => $this->fraudService->unblacklistCustomer($b));

        return response()->json(['message' => 'Customer unblocked.', 'blocked' => false]);
    }

    /** Export the current filtered customer list as CSV. */
    public function export(Request $request): StreamedResponse
    {
        $term = trim((string) $request->query('q', ''));
        $blocked = $this->blockedCustomerIds();

        $query = Customer::query()->withCount('orders')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->orderByDesc('id');

        $filename = 'customers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $blocked) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Address', 'Total Orders', 'Total Spent', 'Status']);
            $query->chunk(200, function ($rows) use ($out, $blocked) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->name, $c->phone, $c->email, $c->address,
                        $c->orders_count, (float) $c->total_spent,
                        in_array($c->id, $blocked, true) ? 'Blocked' : 'Active',
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<int,int> ids of customers with an active blacklist entry (optionally scoped). */
    private function blockedCustomerIds(array $scopeIds = []): array
    {
        return CustomerBlacklist::query()
            ->where('active', true)
            ->whereNotNull('customer_id')
            ->when(! empty($scopeIds), fn ($q) => $q->whereIn('customer_id', $scopeIds))
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isBlocked(Customer $customer): bool
    {
        return CustomerBlacklist::query()->where('active', true)
            ->where(fn ($q) => $q->where('customer_id', $customer->id)->orWhere('phone', $customer->phone))
            ->exists();
    }

    /** Latest order's district per customer (used as the "City" column). */
    private function latestOrderCity(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return Order::query()
            ->whereIn('customer_id', $ids)
            ->orderByDesc('id')
            ->get(['customer_id', 'district', 'address'])
            ->groupBy('customer_id')
            ->map(fn ($rows) => $rows->first()->district ?: null)
            ->all();
    }

    private function behaviorTag(Customer $customer): string
    {
        $delivered = (int) $customer->delivered_orders;
        $bad = (int) $customer->cancelled_orders + (int) $customer->returned_orders;
        $total = max(1, (int) $customer->total_orders);

        if ($bad >= 2 && ($bad / $total) >= 0.4) {
            return 'Risky';
        }
        if ($delivered >= 3) {
            return 'Loyal';
        }

        return 'Regular';
    }
}
