<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OrderNoteController extends Controller
{
    /**
     * "Order Processing Note" — every staff note across all orders, newest
     * first, with an inline form to add a note to any order by number.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['order_number']);

        $notes = OrderNote::query()
            ->with(['order:id,order_number,customer_name,status', 'staffUser:id,name'])
            ->when($filters['order_number'] ?? null, fn ($query, $orderNumber) => $query->whereHas(
                'order',
                fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%'.$orderNumber.'%')
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('studio.orders.notes', [
            'notes' => $notes,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'exists:orders,order_number'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $order = Order::where('order_number', $validated['order_number'])->firstOrFail();

        $order->orderNotes()->create([
            'note' => $validated['note'],
            'staff_user_id' => auth()->guard('staff')->id(),
        ]);

        return back()->with('success', 'Note added to order '.$order->order_number.'.');
    }
}
