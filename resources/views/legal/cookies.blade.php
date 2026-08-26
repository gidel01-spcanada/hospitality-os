@extends('layouts.app')

@section('title', __('messages.legal.cookies'))
@section('seo_description', __('messages.seo.cookies_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container narrow">
        <span class="badge badge-emerald">{{ __('messages.legal.title') }}</span>
        <h1>{{ __('messages.legal.cookies') }}</h1>
    </div>
</section>

<section class="container auth-panel">
    <div class="summary-card full-width">
        <p>{{ __('messages.legal.cookies_one') }}</p>
        <p>{{ __('messages.legal.cookies_two') }}</p>
    </div>
</section>
@endsection
