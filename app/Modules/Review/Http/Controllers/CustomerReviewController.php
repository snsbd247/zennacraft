<?php

namespace App\Modules\Review\Http\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Review\Http\Requests\StoreProductReviewRequest;
use App\Modules\Review\Services\ProductReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CustomerReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function store(StoreProductReviewRequest $request, Order $order): RedirectResponse
    {
        $customer = $this->currentCustomer();

        if (! $customer) {
            return redirect()
                ->route('customer.login')
                ->with('status', 'Please log in before submitting a review.');
        }

        $this->reviews->submit($customer, $order, $request->validated());

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Thanks. Your review is pending moderation.');
    }

    protected function currentCustomer(): ?Customer
    {
        $customerId = session('customer_id');

        return $customerId ? Customer::find($customerId) : null;
    }
}
