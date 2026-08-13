@forelse ($brands as $brand)
    <tr>
        <td>{{ $loop->iteration + ($brands->firstItem() ? $brands->firstItem() - 1 : 0) }}</td>
        <td class="zc-sm-name">{{ $brand->name }}</td>
        <td style="font-weight:700;">{{ $brand->position }}</td>
        <td>
            @php $img = $mediaUrl($brand->image); @endphp
            <span class="zc-br-logo">@if ($img)<img src="{{ $img }}" alt="{{ $brand->name }}">@else {{ strtoupper(mb_substr($brand->name, 0, 2)) }} @endif</span>
        </td>
        <td><span class="zc-sm-pill zc-br-status {{ $brand->isActive() ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $brand->isActive() ? 'Active' : 'De-active' }}</span></td>
        <td>
            <div class="zc-sm-act">
                <a href="{{ route('brands.edit', $brand) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('brands.toggle', $brand) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 13l4 4L19 7"/></svg></button>
                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('brands.destroy', $brand) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
            </div>
        </td>
    </tr>
@empty
    <tr><td colspan="6" class="zc-sm-empty">No brands found. Click <b>+ Brand Add</b> to create one.</td></tr>
@endforelse
