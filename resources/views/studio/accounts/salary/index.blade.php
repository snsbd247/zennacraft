@extends('layouts.studio')
@section('title', 'Employee Salary')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')
<style>
    .zc-emp-av{width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:inline-grid;place-items:center;font-weight:800;color:var(--studio-muted);font-size:0.75rem;overflow:hidden;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('accounts.salary.create') }}" class="studio-command-button studio-command-button--primary">+ Add Member</a></div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Total Monthly Salary: ৳{{ number_format($totalSalary) }}</h2>

        <form method="GET" class="zc-ac-filters" style="grid-template-columns:120px 1fr 160px;">
            <select name="per_page" class="studio-form-control">@foreach ([25,50,100] as $n)<option value="{{ $n }}" @selected(request('per_page',50)==$n)>{{ $n }}</option>@endforeach</select>
            <input type="text" name="q" class="studio-form-control" placeholder="search member name, phone or email" value="{{ request('q') }}">
            <select name="status" class="studio-form-control" onchange="this.form.submit()">
                <option value="active" @selected($status==='active')>Active</option>
                <option value="inactive" @selected($status==='inactive')>Inactive</option>
                <option value="all" @selected($status==='all')>All</option>
            </select>
        </form>

        <div style="overflow-x:auto;">
        <table class="zc-sm-tbl">
            <thead><tr><th>Serial</th><th>Name</th><th>Designation</th><th>Email</th><th>Phone</th><th>Image</th><th>Office</th><th>CV</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($employees as $e)
                    <tr>
                        <td>{{ $loop->iteration + ($employees->firstItem() ? $employees->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $e->name }}</td>
                        <td>{{ $e->position ?: '—' }}</td>
                        <td>{{ $e->email ?: '—' }}</td>
                        <td>{{ $e->phone ?: '—' }}</td>
                        <td>@php $img=$mediaUrl($e->image); @endphp<span class="zc-emp-av">@if($img)<img src="{{ $img }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else{{ strtoupper(mb_substr($e->name,0,1)) }}@endif</span></td>
                        <td>{{ $e->office_phone ?: '—' }}</td>
                        <td>@if($e->cv_path)<a href="{{ asset('storage/'.$e->cv_path) }}" target="_blank" class="zc-sm-btn zc-sm-btn--edit" title="Download CV"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg></a>@else—@endif</td>
                        <td><span class="zc-sm-pill zc-emp-status {{ $e->isActive() ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $e->isActive() ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('accounts.salary.edit', $e) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('accounts.salary.toggle', $e) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('accounts.salary.destroy', $e) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="zc-sm-empty">No members yet. Click <b>+ Add Member</b>.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of total {{ $employees->total() }} entries</div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-emp-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-emp-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-emp-status'); var on=res.d.status==='active'; p.textContent=on?'Active':'Inactive'; p.className='zc-sm-pill zc-emp-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this member?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
        });
    })();
</script>
@endpush
@endsection
