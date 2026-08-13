@extends('layouts.studio')
@section('title', ($employee->exists ? 'Edit' : 'Add').' Member')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')
<style>
    .zc-emp-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.1rem 1.6rem;}
    @media(max-width:720px){.zc-emp-grid{grid-template-columns:1fr;}}
    .zc-emp-f label{display:block;font-weight:700;font-size:0.82rem;margin-bottom:0.35rem;color:var(--studio-text);}
    .zc-emp-prev{width:80px;height:80px;border-radius:12px;object-fit:cover;border:1px solid var(--studio-border);display:none;margin-top:0.5rem;}
    .zc-emp-prev.show{display:block;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('accounts.salary.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="zc-ac-title">{{ $employee->exists ? 'Edit' : 'Add' }} member</h1>
        <form method="POST" enctype="multipart/form-data" action="{{ $employee->exists ? route('accounts.salary.update', $employee) : route('accounts.salary.store') }}" style="margin-top:1rem;">
            @csrf @if($employee->exists) @method('PUT') @endif
            <div class="zc-emp-grid">
                <div class="zc-emp-f"><label>Joining Date</label><input type="date" name="joining_date" class="studio-form-control" value="{{ old('joining_date', optional($employee->joining_date)->format('Y-m-d')) }}"></div>
                <div class="zc-emp-f"><label>Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $employee->name) }}" placeholder="name" required></div>
                <div class="zc-emp-f"><label>Position</label>
                    <select name="position" class="studio-form-control">
                        <option value="">Select Position</option>
                        @foreach ($positions as $p)<option value="{{ $p }}" @selected(old('position', $employee->position) === $p)>{{ $p }}</option>@endforeach
                    </select>
                </div>
                <div class="zc-emp-f"><label>Email</label><input type="email" name="email" class="studio-form-control" value="{{ old('email', $employee->email) }}" placeholder="email"></div>
                <div class="zc-emp-f"><label>Phone</label><input name="phone" class="studio-form-control" value="{{ old('phone', $employee->phone) }}"></div>
                <div class="zc-emp-f"><label>Office phone</label><input name="office_phone" class="studio-form-control" value="{{ old('office_phone', $employee->office_phone) }}"></div>
                <div class="zc-emp-f"><label>Salary (monthly)</label><input type="number" step="0.01" min="0" name="salary" class="studio-form-control" value="{{ old('salary', (float) $employee->salary ?: '') }}"></div>
                <div class="zc-emp-f"><label>Status</label><select name="status" class="studio-form-control"><option value="active" @selected(old('status',$employee->status)==='active')>Active</option><option value="inactive" @selected(old('status',$employee->status)==='inactive')>Inactive</option></select></div>
                <div class="zc-emp-f"><label>Designation</label><textarea name="designation" class="studio-form-control" rows="3" placeholder="write title about this member">{{ old('designation', $employee->designation) }}</textarea></div>
                <div class="zc-emp-f"><label>Image</label><input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-emp-img">
                    @php $img=$mediaUrl($employee->image ?? null); @endphp
                    <img id="zc-emp-prev" class="zc-emp-prev {{ $img ? 'show' : '' }}" src="{{ $img ?: '' }}" alt="">
                </div>
                <div class="zc-emp-f"><label>CV/Resume (doc, pdf or zip)</label><input type="file" name="cv" accept=".pdf,.doc,.docx,.zip" class="studio-form-control">
                    @if($employee->cv_path)<a href="{{ asset('storage/'.$employee->cv_path) }}" target="_blank" style="font-size:0.8rem;color:var(--studio-accent);">current CV ↗</a>@endif
                </div>
            </div>
            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var input=document.getElementById('zc-emp-img'), prev=document.getElementById('zc-emp-prev');
        if(input){ input.addEventListener('change', function(){ var f=input.files[0]; if(f){ prev.src=URL.createObjectURL(f); prev.classList.add('show'); } }); }
    })();
</script>
@endpush
@endsection
