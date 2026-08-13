@extends('layouts.studio')
@section('title', 'Assign Permissions')
@section('subtitle', 'Admin')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-pm-head{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;}
    .zc-pm-name{background:var(--studio-surface-soft);border:1px solid var(--studio-border);border-radius:10px;padding:0.7rem 1rem;font-weight:800;color:var(--studio-text);width:100%;}
    .zc-pm-tools{display:flex;gap:0.5rem;flex-wrap:wrap;margin:0.4rem 0 1.2rem;}
    .zc-pm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;}
    .zc-pm-card{border:1px solid var(--studio-border);border-radius:13px;overflow:hidden;background:var(--studio-surface);}
    .zc-pm-card > header{display:flex;align-items:center;justify-content:space-between;gap:0.5rem;padding:0.6rem 0.85rem;background:var(--studio-surface-soft);border-bottom:1px solid var(--studio-border);}
    .zc-pm-card > header b{font-size:0.82rem;font-weight:800;color:var(--studio-text);text-transform:capitalize;display:flex;align-items:center;gap:6px;}
    .zc-pm-card > header b svg{width:15px;height:15px;color:var(--studio-accent,#1f7a3d);}
    .zc-pm-all{font-size:0.68rem;font-weight:800;color:var(--studio-muted);cursor:pointer;user-select:none;}
    .zc-pm-list{padding:0.6rem 0.85rem;display:grid;gap:0.5rem;}
    .zc-pm-item{display:flex;align-items:center;gap:0.55rem;font-size:0.82rem;color:var(--studio-text);cursor:pointer;}
    .zc-pm-item input{width:1.05rem;height:1.05rem;accent-color:var(--studio-accent,#1f7a3d);flex:none;}
    .zc-pm-save{position:sticky;bottom:0;text-align:center;padding:1.1rem 0 0.4rem;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:320;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route('admins.index') }}" class="studio-command-button">← Back</a>
        <div style="flex:1;"></div>
    </div>

    <div class="studio-card" style="padding:1.5rem 1.75rem;">
        <h1 class="studio-section-title" style="justify-content:center;margin-bottom:1.2rem;">Assign Permissions</h1>

        <label style="font-size:0.78rem;font-weight:700;color:var(--studio-muted);text-transform:uppercase;letter-spacing:.03em;">Admin Name</label>
        <div class="zc-pm-name" style="margin-top:0.35rem;">{{ $admin->name }} <span style="color:var(--studio-muted);font-weight:600;">· {{ $admin->email }}</span></div>

        <div class="zc-pm-tools">
            <button type="button" class="studio-command-button" id="zc-pm-selectall">Select all</button>
            <button type="button" class="studio-command-button" id="zc-pm-clearall">Clear all</button>
            <span style="align-self:center;color:var(--studio-muted);font-size:0.8rem;">Checked permissions are what this admin can access across the panel.</span>
        </div>

        <form id="zc-pm-form" action="{{ route('admins.permissions.save', $admin) }}">
            <div class="zc-pm-grid">
                @foreach ($groups as $module => $perms)
                    <div class="zc-pm-card" data-card>
                        <header>
                            <b><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>{{ str_replace('_', ' ', $module) }}</b>
                            <span class="zc-pm-all" data-cardall>all</span>
                        </header>
                        <div class="zc-pm-list">
                            @foreach ($perms as $perm)
                                <label class="zc-pm-item">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $assigned, true))>
                                    <span>{{ $perm->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="zc-pm-save">
                <button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:3rem;">Submit</button>
            </div>
        </form>
    </div>
</div>
<div class="zc-cat-toast" id="zc-pm-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var form=document.getElementById('zc-pm-form'), toast=document.getElementById('zc-pm-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function allBoxes(){ return Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"]')); }

        document.getElementById('zc-pm-selectall').addEventListener('click', function(){ allBoxes().forEach(function(c){ c.checked=true; }); });
        document.getElementById('zc-pm-clearall').addEventListener('click', function(){ allBoxes().forEach(function(c){ c.checked=false; }); });
        form.querySelectorAll('[data-cardall]').forEach(function(link){
            link.addEventListener('click', function(){
                var boxes=link.closest('[data-card]').querySelectorAll('input[type="checkbox"]');
                var allOn=Array.prototype.every.call(boxes,function(c){return c.checked;});
                boxes.forEach(function(c){ c.checked=!allOn; });
            });
        });

        form.addEventListener('submit', function(e){
            e.preventDefault();
            var ids=allBoxes().filter(function(c){return c.checked;}).map(function(c){return parseInt(c.value,10);});
            var btn=form.querySelector('button[type="submit"]'); btn.disabled=true;
            fetch(form.getAttribute('action'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:JSON.stringify({permissions:ids})})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){ btn.disabled=false; showToast(res.ok?(res.d.message||'Saved'):'Failed', !res.ok); })
                .catch(function(){ btn.disabled=false; showToast('Failed',true); });
        });
    })();
</script>
@endpush
@endsection
