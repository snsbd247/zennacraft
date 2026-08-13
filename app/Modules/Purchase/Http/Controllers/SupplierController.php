<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $supplierService) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->withSum('purchases as purchased_sum', 'total_amount')
            ->withSum('purchases as paid_sum', 'paid_amount')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('studio.suppliers.index', [
            'suppliers' => $suppliers,
            'overview' => $this->supplierService->overview(),
            'search' => $search,
        ]);
    }

    public function show(Supplier $supplier): View
    {
        return view('studio.suppliers.show', [
            'supplier' => $supplier,
            'stats' => $this->supplierService->stats($supplier),
            'purchases' => $supplier->purchases()->withCount('items')->orderByDesc('purchase_date')->orderByDesc('id')->get(),
            'payments' => $supplier->payments()->latest('paid_on')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $supplier = Supplier::create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Supplier "'.$supplier->name.'" added.',
                'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            ]);
        }

        return redirect()->route('purchases.create', ['supplier' => $supplier->id])->with('success', 'Supplier "'.$supplier->name.'" added.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $supplier->update($data);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function storePayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->supplierService->recordPayment($supplier, $data);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Payment recorded for '.$supplier->name.'.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchases()->exists()) {
            return back()->withErrors(['supplier' => 'This supplier has purchase records and cannot be deleted. Set it inactive instead.']);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }
}
