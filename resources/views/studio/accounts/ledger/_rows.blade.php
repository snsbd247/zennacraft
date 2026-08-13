@php $isCredit = $type === 'credit'; @endphp
@forelse ($rows as $row)
    <tr>
        <td>{{ $loop->iteration + ($rows->firstItem() ? $rows->firstItem() - 1 : 0) }}</td>
        <td style="white-space:nowrap;">{{ optional($row->transaction_date)->format('Y-m-d') }}<br><span style="color:var(--studio-muted);font-size:0.75rem;">{{ optional($row->created_at)->format('h:i A') }}</span></td>
        @if ($isCredit)<td style="font-weight:700;">{{ $row->invoice }}</td>@endif
        <td class="zc-sm-name">{{ $row->purpose ?: '—' }}</td>
        <td>{{ $row->account?->name ?: '—' }}</td>
        <td class="{{ $isCredit ? 'zc-money-c' : 'zc-money-d' }}">{{ number_format((float) $row->amount) }}</td>
        <td style="color:var(--studio-muted);max-width:320px;">{{ \Illuminate\Support\Str::limit($row->description, 60) }}</td>
        <td>{{ $row->staffUser?->name ?: 'System' }}</td>
        <td>
            <div class="zc-sm-act">
                <a href="{{ route($isCredit ? 'accounts.income.edit' : 'accounts.expense.edit', $row) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route($isCredit ? 'accounts.income.destroy' : 'accounts.expense.destroy', $row) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
            </div>
        </td>
    </tr>
@empty
    <tr><td colspan="{{ $isCredit ? 9 : 8 }}" class="zc-sm-empty">No records found.</td></tr>
@endforelse
