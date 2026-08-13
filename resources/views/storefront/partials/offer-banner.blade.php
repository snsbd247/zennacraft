{{-- Campaign/Offer > Offer Banner. Renders every active banner for one
     placement ($obPlacement). Expects $storefrontSliders + $mediaUrl in scope. --}}
@php $obBanners = $storefrontSliders->where('placement', $obPlacement)->values(); @endphp
@if ($obBanners->isNotEmpty())
    @once
        @push('storefront-styles')
        <style>
            .zc-obwrap{display:grid;gap:16px;}
            .zc-obwrap--2{grid-template-columns:1fr 1fr;}
            @media (max-width:720px){.zc-obwrap--2{grid-template-columns:1fr;}}
            .zc-ob{position:relative;display:flex;align-items:flex-end;min-height:150px;border-radius:18px;overflow:hidden;background:var(--leaf-soft);background-size:cover;background-position:center;text-decoration:none;box-shadow:0 18px 40px -28px rgba(9,30,17,.5);}
            .zc-ob::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(9,30,17,.62),rgba(9,30,17,.05));}
            .zc-ob__c{position:relative;z-index:2;padding:22px 24px;color:#fff;max-width:70%;}
            .zc-ob__c h3{margin:0;font-size:22px;font-weight:800;line-height:1.15;}
            .zc-ob__c p{margin:6px 0 0;font-size:14px;opacity:.9;}
            .zc-ob__btn{display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:9px 18px;border-radius:999px;background:var(--honey);color:#3a2a00;font-weight:800;font-size:13px;}
        </style>
        @endpush
    @endonce
    <section class="zc-sec zc-wrap">
        <div class="zc-obwrap {{ $obBanners->count() > 1 ? 'zc-obwrap--2' : '' }}">
            @foreach ($obBanners as $ob)
                @php $obImg = $mediaUrl($ob->image); @endphp
                <a class="zc-ob" @if ($ob->button_url) href="{{ $ob->button_url }}" @endif @if ($obImg) style="background-image:linear-gradient(90deg,rgba(9,30,17,.35),rgba(9,30,17,0)),url('{{ $obImg }}');" @endif>
                    @if ($ob->title || $ob->subtitle || $ob->button_text)
                        <div class="zc-ob__c">
                            @if ($ob->title)<h3>{{ $ob->title }}</h3>@endif
                            @if ($ob->subtitle)<p>{{ $ob->subtitle }}</p>@endif
                            @if ($ob->button_text)<span class="zc-ob__btn">{{ $ob->button_text }} <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>@endif
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
