@extends('layouts.studio')

@section('title', 'My Account')
@section('subtitle', 'Profile & security')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('success'))
            <div class="studio-callout studio-callout--success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="studio-callout studio-callout--danger"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <section class="studio-card p-6" id="profile">
            <div class="studio-section-title">Profile</div>
            <p class="studio-section-subtitle">Your Studio account — update your picture, name and phone.</p>

            <form method="POST" action="{{ route('account.profile') }}" enctype="multipart/form-data" class="mt-5">
                @csrf
                <div style="display:flex;gap:1.25rem;align-items:center;flex-wrap:wrap;margin-bottom:1.4rem;">
                    <div style="position:relative;flex:none;">
                        @if ($staffUser->avatar)
                            <img id="zc-acc-avatar" src="{{ $staffUser->avatar }}" alt="" style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:2px solid var(--studio-border);">
                        @else
                            <div id="zc-acc-avatar" style="width:84px;height:84px;border-radius:50%;display:grid;place-items:center;font-size:1.7rem;font-weight:800;color:#fff;background:linear-gradient(135deg,#c79a3b,#a9793f);border:2px solid var(--studio-border);">{{ strtoupper(mb_substr($staffUser->name ?: 'S', 0, 2)) }}</div>
                        @endif
                    </div>
                    <div>
                        <label class="studio-label" for="zc-acc-file">Profile picture</label>
                        <input type="file" id="zc-acc-file" name="avatar" accept="image/*" class="studio-form-control" style="max-width:320px;">
                        <div style="font-size:0.75rem;color:var(--studio-muted);margin-top:0.35rem;">JPG/PNG, up to 4 MB. Shown in the top-right menu.</div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div class="studio-field"><label class="studio-label" for="zc-acc-name">Name</label><input type="text" id="zc-acc-name" name="name" value="{{ old('name', $staffUser->name) }}" class="studio-form-control" required></div>
                    <div class="studio-field"><label class="studio-label" for="zc-acc-phone">Phone</label><input type="text" id="zc-acc-phone" name="phone" value="{{ old('phone', $staffUser->phone) }}" class="studio-form-control" placeholder="01XXXXXXXXX"></div>
                    <div><dt style="color:var(--studio-muted);">Email</dt><dd class="font-semibold" style="color:var(--studio-text);">{{ $staffUser->email }}</dd></div>
                    <div><dt style="color:var(--studio-muted);">Roles</dt><dd class="font-semibold" style="color:var(--studio-text);">{{ $staffUser->roles->pluck('name')->join(', ') ?: '—' }}</dd></div>
                </div>

                <div style="margin-top:1.25rem;"><button type="submit" class="studio-command-button studio-command-button--primary">Save profile</button></div>
            </form>
        </section>

        @push('studio-scripts')
        <script>
            (function(){
                var f=document.getElementById('zc-acc-file'), a=document.getElementById('zc-acc-avatar'); if(!f||!a) return;
                f.addEventListener('change', function(){
                    if(!f.files||!f.files[0]) return;
                    var url=URL.createObjectURL(f.files[0]);
                    if(a.tagName==='IMG'){ a.src=url; }
                    else { var img=document.createElement('img'); img.id='zc-acc-avatar'; img.src=url; img.alt=''; img.style.cssText='width:84px;height:84px;border-radius:50%;object-fit:cover;border:2px solid var(--studio-border);'; a.replaceWith(img); }
                });
            })();
        </script>
        @endpush

        <section class="studio-card p-6" id="password">
            <div class="studio-section-title">Change Password</div>
            <p class="studio-section-subtitle">Use a strong password of at least 8 characters.</p>
            <form method="POST" action="{{ route('account.password') }}" class="mt-5 grid gap-4 sm:max-w-md">
                @csrf
                <div class="studio-field">
                    <label for="current_password" class="studio-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="studio-form-control" required>
                </div>
                <div class="studio-field">
                    <label for="password" class="studio-label">New Password</label>
                    <input type="password" id="password" name="password" class="studio-form-control" required>
                </div>
                <div class="studio-field">
                    <label for="password_confirmation" class="studio-label">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="studio-form-control" required>
                </div>
                <div>
                    <button type="submit" class="studio-command-button studio-command-button--primary">Update Password</button>
                </div>
            </form>
        </section>
    </div>
@endsection
