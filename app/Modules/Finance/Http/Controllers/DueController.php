<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountTransaction;
use App\Modules\Order\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Customer dues: orders whose paid_amount is less than the order total.
 * "Get Paid" records the outstanding amount as a credit and marks the order
 * fully paid.
 */
class DueController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereColumn('paid_amount', '<', 'total')
            ->when($request->string('q')->trim()->value(), fn ($q, $t) => $q->where('customer_phone', 'like', "%{$t}%"))
            ->orderByDesc('created_at')
            ->paginate(50)->withQueryString();

        $totalDue = (float) Order::query()
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereColumn('paid_amount', '<', 'total')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due');

        return view('studio.accounts.due.index', [
            'orders' => $orders,
            'totalDue' => $totalDue,
            'accounts' => Account::where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function getPaid(Request $request, Order $order): RedirectResponse
    {
        $due = round((float) $order->total - (float) $order->paid_amount, 2);
        if ($due <= 0) {
            return back()->with('error', 'This order has no due.');
        }
        $accountId = $request->integer('account_id') ?: Account::where('slug', 'cash')->value('id');

        AccountTransaction::create([
            'account_id' => $accountId,
            'type' => AccountTransaction::TYPE_CREDIT,
            'purpose' => 'Due amount paid',
            'amount' => $due,
            'description' => 'Due paid for order '.$order->order_number,
            'transaction_date' => now()->toDateString(),
            'staff_user_id' => auth()->guard('staff')->id(),
        ]);

        $order->update(['paid_amount' => $order->total]);

        return back()->with('success', 'Due of ৳'.number_format($due).' collected.');
    }
}
