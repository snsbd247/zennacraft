@extends('layouts.studio')
@section('title', 'Admin')
@section('subtitle', 'Admin')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-ad-toolbar{display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;margin-bottom:1.2rem;}
    .zc-ad-toolbar .grow{flex:1;min-width:180px;}
    .zc-ad-img{width:46px;height:46px;border-radius:10px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);}
    .zc-ad-initial{width:46px;height:46px;border-radius:10px;display:grid;place-items:center;font-weight:800;color:#fff;background:linear-gradient(135deg,#1f7a3d,#155e2e);}
    .zc-ad-btn{border:none;border-radius:8px;padding:0.42rem 0.75rem;font-weight:700;font-size:0.76rem;cursor:pointer;color:#fff;}
    .zc-ad-btn--reset{background:#e0a12a;} .zc-ad-btn--perm{background:#3b57a5;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:320;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
    .zc-modal{position:fixed;inset:0;z-index:300;background:rgba(15,23,42,.5);display:none;align-items:flex-start;justify-content:center;padding:8vh 1rem;}
    .zc-modal.show{display:flex;}
    .zc-modal__box{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:16px;width:min(430px,100%);box-shadow:0 30px 60px -25px rgba(0,0,0,.5);}
    .zc-modal__box header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--studio-border);}
    .zc-modal__box header b{font-size:0.95rem;color:var(--studio-text);}
    .zc-modal__box header button{border:none;background:none;font-size:1.3rem;line-height:1;color:var(--studio-muted);cursor:pointer;}
    .zc-modal__box .body{padding:1.1rem 1.2rem;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif

    <div class="zc-sm-head">
        <a href="{{ route('admins.create') }}" class="studio-command-button studio-command-button--primary">+ Add Admin</a>
        <div style="flex:1;"></div>
    </div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <form method="GET" action="{{ route('admins.index') }}" class="zc-ad-toolbar">
            <h1 class="studio-section-title" style="margin:0;">Admin</h1>
            <input type="search" name="q" value="{{ $term }}" class="studio-form-control grow" placeholder="type  name or email">
            <select name="status" class="studio-form-control" style="max-width:150px;" onchange="this.form.submit()">
                @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
        </form>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Image</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($admins as $admin)
                    <tr data-row="{{ $admin->id }}">
                        <td>{{ $loop->iteration + ($admins->firstItem() ? $admins->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $admin->name }}</td>
                        <td>{{ $admin->email }}</td>
                        <td>{{ $admin->phone ?: 'empty' }}</td>
                        <td>
                            @if ($admin->avatar)<img src="{{ $admin->avatar }}" alt="" class="zc-ad-img">
                            @else<span class="zc-ad-initial">{{ strtoupper(mb_substr($admin->name, 0, 1)) }}</span>@endif
                        </td>
                        <td><span class="zc-sm-pill zc-ad-status {{ $admin->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ ucfirst($admin->status) }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('admins.edit', $admin) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                @if ($admin->id !== $currentId)
                                    <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Enable / disable" data-toggle="{{ route('admins.toggle', $admin) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                @endif
                                <button type="button" class="zc-ad-btn zc-ad-btn--reset" data-reset="{{ route('admins.reset-password', $admin) }}" data-name="{{ $admin->name }}">Reset Password</button>
                                <a href="{{ route('admins.permissions', $admin) }}" class="zc-ad-btn zc-ad-btn--perm" style="text-decoration:none;">Permissions</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="zc-sm-empty">No admins found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="zc-sm-pager">
            <span>Showing {{ $admins->firstItem() ?? 0 }} to {{ $admins->lastItem() ?? 0 }} of total {{ $admins->total() }} entries</span>
            @if ($admins->hasPages())
                <span style="margin-left:auto;"></span>
                @if (!$admins->onFirstPage())<a href="{{ $admins->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif
                @if ($admins->hasMorePages())<a href="{{ $admins->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif
            @endif
        </div>
    </div>
</div>

<div class="zc-modal" id="zc-ad-modal">
    <div class="zc-modal__box">
        <header><b>Reset password — <span id="zc-ad-modal-name"></span></b><button type="button" id="zc-ad-modal-close">✕</button></header>
        <div class="body">
            <div class="zc-sm-field"><label>New password</label><input type="password" id="zc-ad-newpass" class="studio-form-control" placeholder="At least 8 characters" autocomplete="new-password"></div>
            <div style="text-align:right;margin-top:0.5rem;"><button type="button" class="studio-command-button studio-command-button--primary" id="zc-ad-savepass">Save password</button></div>
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-ad-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-ad-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function post(url,body){ return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:JSON.stringify(body||{})}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }

        var modal=document.getElementById('zc-ad-modal'), passInput=document.getElementById('zc-ad-newpass'), modalName=document.getElementById('zc-ad-modal-name');
        var currentReset=null;
        document.getElementById('zc-ad-modal-close').addEventListener('click', function(){ modal.classList.remove('show'); });
        modal.addEventListener('click', function(e){ if(e.target===modal) modal.classList.remove('show'); });

        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ post(tog.getAttribute('data-toggle')).then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-ad-status'); var on=res.d.status==='active'; p.textContent=on?'Active':'Inactive'; p.className='zc-sm-pill zc-ad-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
            var rst=e.target.closest('[data-reset]');
            if(rst){ currentReset=rst.getAttribute('data-reset'); modalName.textContent=rst.getAttribute('data-name'); passInput.value=''; modal.classList.add('show'); passInput.focus(); return; }
        });
        document.getElementById('zc-ad-savepass').addEventListener('click', function(){
            if(!currentReset) return;
            if((passInput.value||'').length<8){ showToast('Password must be at least 8 characters',true); return; }
            post(currentReset,{password:passInput.value}).then(function(res){ if(res.ok){ modal.classList.remove('show'); showToast(res.d.message||'Password reset'); } else showToast((res.d&&res.d.message)||'Failed',true); });
        });
    })();
</script>
@endpush
@endsection
