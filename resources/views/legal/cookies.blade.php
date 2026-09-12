@extends('layouts.app')

@section('title', __('messages.legal.cookies'))
@section('seo_description', __('messages.seo.cookies_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container">
        <span class="badge badge-emerald">{{ __('messages.legal.title') }}</span>
        <h1>{{ __('messages.legal.cookies') }}</h1>
        <p>{{ __('messages.legal.cookies_subtitle') }}</p>
    </div>
</section>

<section class="container page-content">
    <div class="summary-card full-width legal-document">
        <article class="legal-article">
            <h2>{{ __('messages.legal.cookies_art1_title') }}</h2>
            <p>{{ __('messages.legal.cookies_art1_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.cookies_art2_title') }}</h2>
            <p>{{ __('messages.legal.cookies_art2_body') }}</p>
            <ul>
                <li>{{ __('messages.legal.cookies_art2_item1') }}</li>
                <li>{{ __('messages.legal.cookies_art2_item2') }}</li>
                <li>{{ __('messages.legal.cookies_art2_item3') }}</li>
            </ul>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.cookies_art3_title') }}</h2>
            <p>{{ __('messages.legal.cookies_art3_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.cookies_art4_title') }}</h2>
            <p>{{ __('messages.legal.cookies_art4_body') }}</p>
        </article>
    </div>
</section>
@endsection
