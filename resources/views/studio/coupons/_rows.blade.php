@php
    $typeLabel = ['fixed' => 'Flat ৳', 'percentage' => 'Percent %', 'free_shipping' => 'Free shipping'];
@endphp
@forelse ($coupons as $coupon)
    <tr>
        <td>{{ $loop->iteration + ($coupons->firstItem() ? $coupons->firstItem() - 1 : 0) }}</td>
        <td><span class="zc-cp-code">{{ $coupon->code }}</span><div style="font-size:0.75rem;color:var(--studio-muted);margin-top:2px;">{{ $coupon->name }}</div></td>
        <td>{{ $typeLabel[$coupon->discount_type] ?? $coupon->discount_type }}</td>
        <td style="font-weight:700;">
            @if ($coupon->discount_type === 'percentage'){{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.') }}%@if($coupon->max_discount_amount) <span style="font-weight:500;color:var(--studio-muted);font-size:0.72rem;">(max ৳{{ number_format((float) $coupon->max_discount_amount) }})</span>@endif
            @elseif ($coupon->discount_type === 'fixed')৳{{ number_format((float) $coupon->discount_value) }}
            @else —
            @endif
        </td>
        <td>{{ (float) $coupon->min_order_amount > 0 ? '৳'.number_format((float) $coupon->min_order_amount) : '—' }}</td>
        <td>{{ $coupon->usages_count }}@if($coupon->usage_limit) / {{ $coupon->usage_limit }}@endif</td>
        <td><span class="zc-sm-pill zc-cp-status {{ $coupon->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ ucfirst($coupon->status) }}</span></td>
        <td>
            <div class="zc-sm-act">
                <a href="{{ route('coupons.edit', $coupon) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('coupons.toggle', $coupon) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('coupons.destroy', $coupon) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
            </div>
        </td>
    </tr>
@empty
    <tr><td colspan="8" class="zc-sm-empty">No coupons yet. Click <b>+ Add Coupon</b> to create one.</td></tr>
@endforelse
