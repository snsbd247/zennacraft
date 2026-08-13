@extends('layouts.studio')
@section('title', $admin->exists ? 'Edit Admin' : 'Add Admin')
@section('subtitle', 'Admin')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-af{max-width:640px;margin:0 auto;}
    .zc-af-row{display:grid;grid-template-columns:150px 1fr;gap:1rem;align-items:center;margin-bottom:1.1rem;}
    @media (max-width:620px){.zc-af-row{grid-template-columns:1fr;gap:0.4rem;}}
    .zc-af-row > label{font-weight:700;color:var(--studio-text);font-size:0.9rem;}
    .zc-af-avatar{width:80px;height:80px;border-radius:14px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);margin-bottom:0.5rem;display:block;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="zc-sm-head">
        <a href="{{ route('admins.index') }}" class="studio-command-button">← Back</a>
        <div style="flex:1;"></div>
    </div>

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="justify-content:center;margin-bottom:1.5rem;">{{ $admin->exists ? 'Edit Admin' : 'Add Admin' }}</h1>

        <form class="zc-af" method="POST" enctype="multipart/form-data" action="{{ $admin->exists ? route('admins.update', $admin) : route('admins.store') }}">
            @csrf
            @if ($admin->exists) @method('PUT') @endif

            <div class="zc-af-row"><label>Name</label><input type="text" name="name" value="{{ old('name', $admin->name) }}" class="studio-form-control" placeholder="name" required></div>
            <div class="zc-af-row"><label>Email</label><input type="email" name="email" value="{{ old('email', $admin->email) }}" class="studio-form-control" placeholder="email" required></div>
            <div class="zc-af-row"><label>Phone</label><input type="text" name="phone" value="{{ old('phone', $admin->phone) }}" class="studio-form-control" placeholder="01XXXXXXXXX"></div>
            <div class="zc-af-row"><label>Password</label><input type="password" name="password" class="studio-form-control" placeholder="{{ $admin->exists ? 'leave blank to keep current' : 'password' }}" autocomplete="new-password" {{ $admin->exists ? '' : 'required' }}></div>
            <div class="zc-af-row">
                <label>Image</label>
                <div>
                    @if ($admin->avatar)<img src="{{ $admin->avatar }}" alt="" class="zc-af-avatar" id="zc-af-prev">@endif
                    <input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-af-img">
                </div>
            </div>
            <div class="zc-af-row">
                <label>Status</label>
                <select name="status" class="studio-form-control">
                    <option value="active" @selected(old('status', $admin->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $admin->status) === 'inactive')>Inactive</option>
                </select>
            </div>

            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:3rem;">Submit</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var input=document.getElementById('zc-af-img'); if(!input) return;
        input.addEventListener('change', function(){
            if(!input.files||!input.files[0]) return;
            var u=URL.createObjectURL(input.files[0]), prev=document.getElementById('zc-af-prev');
            if(prev){ prev.src=u; } else { var img=document.createElement('img'); img.src=u; img.id='zc-af-prev'; img.className='zc-af-avatar'; input.parentNode.insertBefore(img,input); }
        });
    })();
</script>
@endpush
@endsection
