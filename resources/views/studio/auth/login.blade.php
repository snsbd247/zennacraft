@php
    try {
        $theme = app(\App\Modules\Theme\Services\ThemeService::class);
        $logo = $theme->mediaUrl('site_logo');
        $store = $theme->get('brand_name') ?: (app(\App\Modules\Settings\Services\SettingService::class)->get('general', 'site_name') ?: 'Zenna Craft');
    } catch (\Throwable $e) {
        $logo = null; $store = 'Zenna Craft';
    }
    $initial = strtoupper(mb_substr($store, 0, 1)) ?: 'Z';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Staff Sign In — {{ $store }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        :root{
            --bg1:#0a0e13; --bg2:#0e1712; --card:rgba(19,25,31,.72);
            --gold:#e6c483; --gold-deep:#c79a3b; --green:#33c07a; --green-deep:#1c8a4e;
            --line:rgba(230,196,131,.16); --text:#eef1f4; --muted:#93a0ac;
        }
        html,body{height:100%;}
        body{
            font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
            background:var(--bg1); color:var(--text); min-height:100dvh;
            display:grid; place-items:center; padding:24px; overflow:hidden; position:relative;
            -webkit-font-smoothing:antialiased;
        }
        /* ---------- Animated aurora background ---------- */
        .aurora{position:fixed;inset:0;z-index:0;overflow:hidden;background:
            radial-gradient(1200px 800px at 50% -10%, #16241c 0%, transparent 60%),
            linear-gradient(160deg, var(--bg1), var(--bg2));}
        .blob{position:absolute;border-radius:50%;filter:blur(70px);opacity:.5;will-change:transform;}
        .blob.b1{width:52vw;height:52vw;left:-12vw;top:-14vw;background:radial-gradient(circle,#1c8a4e,transparent 68%);animation:drift1 20s ease-in-out infinite;}
        .blob.b2{width:44vw;height:44vw;right:-12vw;top:8vh;background:radial-gradient(circle,#c79a3b,transparent 66%);opacity:.34;animation:drift2 24s ease-in-out infinite;}
        .blob.b3{width:40vw;height:40vw;left:22vw;bottom:-20vw;background:radial-gradient(circle,#0f5c8a,transparent 66%);opacity:.3;animation:drift1 28s ease-in-out infinite reverse;}
        .grid-ov{position:fixed;inset:0;z-index:0;opacity:.5;
            background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
            background-size:44px 44px;mask-image:radial-gradient(circle at 50% 40%,#000 0%,transparent 72%);-webkit-mask-image:radial-gradient(circle at 50% 40%,#000 0%,transparent 72%);}
        /* floating sparks */
        .spark{position:fixed;z-index:0;width:5px;height:5px;border-radius:50%;background:var(--gold);opacity:0;box-shadow:0 0 10px 1px rgba(230,196,131,.7);animation:spark 7s linear infinite;}

        /* ---------- Card ---------- */
        .wrap{position:relative;z-index:2;width:100%;max-width:400px;}
        .card{position:relative;background:var(--card);backdrop-filter:blur(22px) saturate(120%);-webkit-backdrop-filter:blur(22px) saturate(120%);
            border:1px solid var(--line);border-radius:24px;padding:38px 32px 32px;
            box-shadow:0 40px 90px -30px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.06);
            animation:cardIn .75s cubic-bezier(.2,.85,.25,1) both;}
        /* glowing top hairline */
        .card::before{content:"";position:absolute;top:0;left:16%;right:16%;height:1px;
            background:linear-gradient(90deg,transparent,var(--gold),transparent);opacity:.7;}

        .brand{display:flex;flex-direction:column;align-items:center;text-align:center;margin-bottom:26px;}
        .badge{position:relative;width:66px;height:66px;display:grid;place-items:center;margin-bottom:16px;animation:floaty 4.5s ease-in-out infinite;}
        .badge__ring{position:absolute;inset:-5px;border-radius:20px;background:conic-gradient(from 0deg,var(--gold),transparent 30%,var(--green) 55%,transparent 80%,var(--gold));animation:spin 6s linear infinite;filter:blur(.5px);}
        .badge__inner{position:relative;width:100%;height:100%;border-radius:17px;display:grid;place-items:center;overflow:hidden;
            background:linear-gradient(150deg,#1b232c,#0f151b);border:1px solid rgba(255,255,255,.08);z-index:1;}
        .badge__inner img{width:100%;height:100%;object-fit:cover;}
        .badge__inner span{font-size:28px;font-weight:800;background:linear-gradient(135deg,#f8ecc9,var(--gold-deep));-webkit-background-clip:text;background-clip:text;color:transparent;}
        .eyebrow{font-size:11px;font-weight:800;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);}
        .title{font-size:25px;font-weight:800;margin-top:8px;letter-spacing:-.01em;}
        .sub{font-size:13.5px;color:var(--muted);margin-top:7px;}

        .field{position:relative;margin-bottom:15px;}
        .field label{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
        .field .box{position:relative;display:flex;align-items:center;}
        .field svg.i{position:absolute;left:14px;width:18px;height:18px;color:var(--muted);pointer-events:none;transition:color .18s ease;}
        .field input{width:100%;height:50px;padding:0 14px 0 42px;border-radius:13px;border:1.5px solid rgba(255,255,255,.1);
            background:rgba(255,255,255,.04);color:var(--text);font-size:15px;font-family:inherit;outline:none;
            transition:border-color .2s ease,box-shadow .2s ease,background .2s ease;}
        .field input::placeholder{color:#5f6b76;}
        .field input:focus{border-color:var(--gold);background:rgba(230,196,131,.06);box-shadow:0 0 0 4px rgba(230,196,131,.14);}
        .field input:focus ~ svg.i{color:var(--gold);}
        .field .peek{position:absolute;right:8px;width:34px;height:34px;display:grid;place-items:center;border:none;background:transparent;color:var(--muted);cursor:pointer;border-radius:9px;transition:color .15s ease,background .15s ease;}
        .field .peek:hover{color:var(--gold);background:rgba(255,255,255,.05);}
        .field input[type=password]{padding-right:44px;} .field input#password{padding-right:44px;}

        .row{display:flex;align-items:center;justify-content:space-between;margin:4px 0 20px;}
        .remember{display:inline-flex;align-items:center;gap:9px;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;user-select:none;}
        .remember input{appearance:none;-webkit-appearance:none;width:18px;height:18px;border-radius:6px;border:1.5px solid rgba(255,255,255,.22);background:rgba(255,255,255,.04);cursor:pointer;position:relative;transition:all .15s ease;}
        .remember input:checked{background:linear-gradient(135deg,var(--gold),var(--gold-deep));border-color:transparent;}
        .remember input:checked::after{content:"";position:absolute;left:5px;top:1.5px;width:5px;height:9px;border:solid #201603;border-width:0 2px 2px 0;transform:rotate(45deg);}

        .btn{position:relative;width:100%;height:52px;border:none;border-radius:14px;cursor:pointer;overflow:hidden;
            background:linear-gradient(135deg,#f8ecc9 0%,var(--gold) 45%,var(--gold-deep) 100%);color:#201603;
            font-family:inherit;font-size:15.5px;font-weight:800;letter-spacing:.02em;
            box-shadow:0 16px 34px -14px rgba(199,154,59,.75);transition:transform .14s ease,box-shadow .14s ease,filter .14s ease;}
        .btn:hover{transform:translateY(-2px);box-shadow:0 22px 44px -14px rgba(199,154,59,.85);filter:brightness(1.04);}
        .btn:active{transform:translateY(0);}
        .btn::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:45%;transform:skewX(-20deg);
            background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);animation:shine 3.6s ease-in-out infinite;}
        .btn[disabled]{opacity:.75;cursor:progress;}
        .btn.is-loading .btn__t{opacity:0;}
        .btn__spin{position:absolute;inset:0;display:none;place-items:center;}
        .btn.is-loading .btn__spin{display:grid;}
        .btn__spin i{width:20px;height:20px;border-radius:50%;border:2.5px solid rgba(32,22,3,.35);border-top-color:#201603;animation:spin .7s linear infinite;}

        .alert{background:rgba(224,72,58,.14);border:1px solid rgba(224,72,58,.4);color:#ffb3aa;border-radius:13px;padding:12px 15px;margin-bottom:20px;font-size:13px;line-height:1.5;animation:rise .4s ease both;}
        .alert b{color:#ffd0c9;display:block;margin-bottom:3px;font-size:12.5px;}
        .alert ul{margin:0;padding-left:16px;}
        .foot{text-align:center;margin-top:22px;font-size:12px;color:#5f6b76;}
        .foot b{color:var(--muted);font-weight:700;}
        .skip{position:absolute;left:12px;top:12px;z-index:20;background:#fff;color:#0f172a;padding:8px 14px;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none;transform:translateY(-180%);transition:transform .2s ease;}
        .skip:focus{transform:none;outline:2px solid var(--gold);}

        /* staggered entrance */
        .stg{opacity:0;animation:rise .6s cubic-bezier(.2,.8,.2,1) both;}
        .d1{animation-delay:.14s;} .d2{animation-delay:.22s;} .d3{animation-delay:.30s;}
        .d4{animation-delay:.38s;} .d5{animation-delay:.46s;} .d6{animation-delay:.54s;}

        @keyframes drift1{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(7%,-5%) scale(1.14);}}
        @keyframes drift2{0%,100%{transform:translate(0,0) scale(1.06);}50%{transform:translate(-6%,6%) scale(1);}}
        @keyframes cardIn{from{opacity:0;transform:translateY(26px) scale(.975);}to{opacity:1;transform:none;}}
        @keyframes rise{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:none;}}
        @keyframes floaty{0%,100%{transform:translateY(0);}50%{transform:translateY(-7px);}}
        @keyframes spin{to{transform:rotate(360deg);}}
        @keyframes shine{0%{left:-60%;}55%,100%{left:130%;}}
        @keyframes spark{0%{opacity:0;transform:translateY(0) scale(.6);}12%{opacity:.9;}100%{opacity:0;transform:translateY(-120px) scale(1);}}

        @media(max-width:440px){ .card{padding:30px 22px 26px;} .title{font-size:22px;} }
        @media(prefers-reduced-motion:reduce){
            .blob,.spark,.badge,.badge__ring,.btn::after,.card,.stg,.badge{animation:none!important;}
            .stg{opacity:1;}
        }
    </style>
</head>
<body>
    <a href="#main-content" class="skip">Skip to content</a>
    <div class="aurora">
        <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
    </div>
    <div class="grid-ov"></div>
    <span class="spark" style="left:18%;bottom:20%;animation-delay:.4s;"></span>
    <span class="spark" style="left:76%;bottom:14%;animation-delay:2.1s;"></span>
    <span class="spark" style="left:42%;bottom:8%;animation-delay:3.6s;"></span>
    <span class="spark" style="left:60%;bottom:26%;animation-delay:5s;"></span>

    <main class="wrap" id="main-content">
        <div class="card">
            <div class="brand">
                <div class="badge">
                    <span class="badge__ring"></span>
                    <span class="badge__inner">
                        @if ($logo)<img src="{{ $logo }}" alt="{{ $store }}">@else<span>{{ $initial }}</span>@endif
                    </span>
                </div>
                <div class="eyebrow stg d1">{{ $store }} Studio</div>
                <h1 class="title stg d2">Staff Sign In</h1>
                <p class="sub stg d3">Sign in with your staff account to continue.</p>
            </div>

            @if (isset($errors) && $errors->any())
                <div class="alert">
                    <b>Please check your details</b>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if (session('status'))
                <div class="alert" style="background:rgba(51,192,122,.14);border-color:rgba(51,192,122,.4);color:#a7e8c4;">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ url(config('admin.path').'/login') }}" data-login-form>
                @csrf
                <div class="field stg d4">
                    <label for="email">Email</label>
                    <div class="box">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="field stg d5">
                    <label for="password">Password</label>
                    <div class="box">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="peek" data-peek aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="row stg d6">
                    <label class="remember"><input type="checkbox" name="remember"> Remember me</label>
                </div>

                <button type="submit" class="btn stg d6" data-login-btn>
                    <span class="btn__t">Sign In</span>
                    <span class="btn__spin"><i></i></span>
                </button>
            </form>
        </div>
        <div class="foot">Protected area · <b>{{ $store }}</b> admin workspace</div>
    </main>

    <script>
        (function () {
            // Password show / hide
            var peek = document.querySelector('[data-peek]');
            var pass = document.getElementById('password');
            if (peek && pass) peek.addEventListener('click', function () {
                var show = pass.type === 'password';
                pass.type = show ? 'text' : 'password';
                peek.innerHTML = show
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/><path d="M9.9 4.6A9.7 9.7 0 0 1 12 4.4c6.5 0 10 7.6 10 7.6a17 17 0 0 1-3 4"/><path d="M6.6 6.6A16.6 16.6 0 0 0 2 12s3.5 7.6 10 7.6a9.6 9.6 0 0 0 4-.9"/></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
                pass.focus();
            });

            // Loading state on submit
            var form = document.querySelector('[data-login-form]');
            var btn = document.querySelector('[data-login-btn]');
            if (form && btn) form.addEventListener('submit', function () {
                btn.classList.add('is-loading'); btn.disabled = true;
            });
        })();
    </script>
</body>
</html>
