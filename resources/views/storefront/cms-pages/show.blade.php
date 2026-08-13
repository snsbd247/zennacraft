@extends('layouts.app')
@section('title', $cmsPage->title.' — '.$storeName)
@section('content')
<section class="zc-pagehero"><div class="zc-wrap"><div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <span>{{ $cmsPage->title }}</span></div><h1>{{ $cmsPage->title }}</h1></div></section>
<section class="zc-sec zc-wrap" style="max-width:820px;">
    <div class="zc-card" style="padding:32px;line-height:1.8;color:var(--ink);">{!! $cmsPage->content !!}</div>
</section>
@endsection
