{{-- Campaign/Offer > Offer Banner. Renders every active banner for one
     placement ($obPlacement). Expects $storefrontSliders + $mediaUrl in scope. --}}
@php $obBanners = $storefrontSliders->where('placement', $obPlacement)->values(); @endphp
@if ($obBanners->isNotEmpty())
    {{-- .zc-ob* CSS moved to public/assets/storefront.css. --}}
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
