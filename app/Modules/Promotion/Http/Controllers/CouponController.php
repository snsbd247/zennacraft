<?php

namespace App\Modules\Promotion\Http\Controllers;

use App\Modules\Promotion\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Studio manager for discount coupons. Coupons created here are validated and
 * applied at cart/checkout by the existing CouponService — no extra wiring.
 */
class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $coupons = Coupon::withCount('usages')
            ->when($request->string('q')->trim()->value(), fn ($query, $t) => $query->where(fn ($w) => $w->where('code', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%")))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return $request->boolean('partial')
            ? view('studio.coupons._rows', ['coupons' => $coupons])
            : view('studio.coupons.index', ['coupons' => $coupons]);
    }

    public function create(): View
    {
        return view('studio.coupons.form', ['coupon' => new Coupon(['discount_type' => 'percentage', 'status' => 'active', 'applies_to' => 'all', 'min_order_amount' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::create($this->validated($request) + ['created_by' => auth()->guard('staff')->id(), 'applies_to' => 'all']);

        return redirect()->route('coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('studio.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon->id));

        return redirect()->route('coupons.index')->with('success', 'Coupon updated.');
    }

    public function toggleStatus(Request $request, Coupon $coupon): JsonResponse|RedirectResponse
    {
        $coupon->update(['status' => $coupon->status === 'active' ? 'inactive' : 'active']);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'status' => $coupon->status])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse|RedirectResponse
    {
        if ($coupon->usages()->exists()) {
            $message = 'This coupon has already been used and cannot be deleted. Disable it instead.';

            return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
        }
        $coupon->delete();

        return $request->expectsJson() ? response()->json(['message' => 'Coupon deleted.']) : back()->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', Rule::in(Coupon::DISCOUNT_TYPES)],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'required_unless:discount_type,free_shipping', $request->input('discount_type') === 'percentage' ? 'max:100' : 'max:9999999'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        if ($data['discount_type'] === 'free_shipping') {
            $data['discount_value'] = 0;
        }

        return $data;
    }
}
