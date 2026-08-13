@extends('layouts.studio')
@section('title', 'Balance')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')
<style>
    .zc-modal{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;background:rgba(15,23,20,.55);}
    .zc-modal.show{display:flex;}
    .zc-modal__box{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:14px;padding:1.5rem;width:min(400px,92vw);box-shadow:0 30px 60px -20px rgba(0,0,0,.5);}
    .zc-bal-act .zc-sm-btn--off{background:#516170;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div><button type="button" class="studio-command-button studio-command-button--primary" onclick="zcBal('','')">+ Add Balance</button></div>

    <div class="studio-card" style="padding:1.5rem;">
        <h2 class="studio-section-title" style="margin-bottom:1.2rem;">Accounts</h2>
        <div class="zc-ac-overview">
            <div class="zc-ac-col">
                <h4>Today Credit</h4>
                @foreach ($accounts as $a)<div class="zc-ac-line"><span>{{ $a->name }} :</span><b class="zc-money-c">{{ number_format((float) ($todayCredit[$a->id] ?? 0)) }}</b></div>@endforeach
                <div class="zc-ac-line total"><span>Total :</span><b>{{ number_format($todayCreditTotal, 2) }}</b></div>
                <div class="zc-ac-sumbtn">TODAY CREDIT&nbsp;·&nbsp;{{ number_format($todayCreditTotal) }}</div>
            </div>
            <div class="zc-ac-col">
                <h4>Today Debit</h4>
                @foreach ($accounts as $a)<div class="zc-ac-line"><span>{{ $a->name }} :</span><b class="zc-money-d">{{ number_format((float) ($todayDebit[$a->id] ?? 0)) }}</b></div>@endforeach
                <div class="zc-ac-line total"><span>Total :</span><b>{{ number_format($todayDebitTotal, 2) }}</b></div>
                <div class="zc-ac-sumbtn" style="background:linear-gradient(135deg,#c0392b,#e0483d);">TODAY DEBIT&nbsp;·&nbsp;{{ number_format($todayDebitTotal) }}</div>
            </div>
            <div class="zc-ac-col">
                <h4>Total Balance</h4>
                @foreach ($accounts as $a)<div class="zc-ac-line"><span>In {{ $a->name }} :</span><b>{{ number_format((float) ($total[$a->id] ?? 0)) }}</b></div>@endforeach
                <div class="zc-ac-line total"><span>In Total :</span><b>{{ number_format($grandTotal, 2) }}</b></div>
                <div class="zc-ac-sumbtn" style="background:linear-gradient(135deg,#1f6f8b,#2a94b6);">TOTAL BALANCE&nbsp;·&nbsp;{{ number_format($grandTotal) }}</div>
            </div>
        </div>
    </div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Balances</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @foreach ($accounts as $a)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="zc-sm-name">{{ $a->name }} @unless($a->active)<span class="zc-sm-pill zc-sm-pill--off" style="margin-left:8px;">Disabled</span>@endunless</td>
                        <td>
                            <div class="zc-sm-act zc-bal-act">
                                <button type="button" class="zc-sm-btn {{ $a->active ? 'zc-sm-btn--tog' : 'zc-sm-btn--off' }}" title="Enable/Disable" data-toggle="{{ route('accounts.balance.toggle', $a) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--edit" title="Edit" onclick="zcBal('{{ $a->id }}', @js($a->name))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('accounts.balance.destroy', $a) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="zc-modal" id="zc-bal-modal">
    <div class="zc-modal__box">
        <h3 style="font-weight:800;font-size:1.05rem;margin-bottom:1rem;" id="zc-bal-title">Add Balance</h3>
        <form method="POST" id="zc-bal-form" action="{{ route('accounts.balance.store') }}">
            @csrf
            <input type="hidden" name="_method" id="zc-bal-method" value="POST">
            <label style="font-size:0.8rem;font-weight:700;">Name</label>
            <input name="name" id="zc-bal-name" class="studio-form-control" style="margin:0.4rem 0 1.1rem;" placeholder="e.g. Cash / City Bank" required>
            <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
                <button type="button" class="studio-command-button" onclick="document.getElementById('zc-bal-modal').classList.remove('show')">Cancel</button>
                <button type="submit" class="studio-command-button studio-command-button--primary">Save</button>
            </div>
        </form>
    </div>
</div>
<div class="zc-cat-toast" id="zc-bal-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    var zcBalStore='{{ route('accounts.balance.store') }}';
    function zcBal(id, name){
        var f=document.getElementById('zc-bal-form');
        document.getElementById('zc-bal-title').textContent=id?'Edit Balance':'Add Balance';
        document.getElementById('zc-bal-name').value=name||'';
        document.getElementById('zc-bal-method').value=id?'PUT':'POST';
        f.action=id?('{{ url('studio/accounts/balance') }}/'+id):zcBalStore;
        document.getElementById('zc-bal-modal').classList.add('show');
    }
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-bal-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ location.reload(); } else showToast(res.d.message||'Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this channel?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
        });
    })();
</script>
@endpush
@endsection
