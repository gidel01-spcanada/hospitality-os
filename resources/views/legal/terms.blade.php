@extends('layouts.app')

@section('title', __('messages.legal.terms'))
@section('seo_description', __('messages.seo.terms_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container">
        <span class="badge badge-emerald">{{ __('messages.legal.title') }}</span>
        <h1>{{ __('messages.legal.terms') }}</h1>
    </div>
</section>

<section class="container page-content">
    <div class="summary-card full-width">
        <p>{{ __('messages.legal.terms_one') }}</p>
        <p>{{ __('messages.legal.terms_two') }}</p>
    </div>
</section>
@endsection
