@extends('layouts.app')

@section('title', __('messages.legal.terms'))
@section('seo_description', __('messages.seo.terms_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container">
        <span class="badge badge-emerald">{{ __('messages.legal.title') }}</span>
        <h1>{{ __('messages.legal.terms') }}</h1>
        <p>{{ __('messages.legal.terms_subtitle') }}</p>
    </div>
</section>

<section class="container page-content">
    <div class="summary-card full-width legal-document">
        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art1_title') }}</h2>
            <p>{{ __('messages.legal.terms_art1_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art2_title') }}</h2>
            <p>{{ __('messages.legal.terms_art2_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art3_title') }}</h2>
            <p>{{ __('messages.legal.terms_art3_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art4_title') }}</h2>
            <p>{{ __('messages.legal.terms_art4_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art5_title') }}</h2>
            <p>{{ __('messages.legal.terms_art5_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art6_title') }}</h2>
            <p>{{ __('messages.legal.terms_art6_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art7_title') }}</h2>
            <p>{{ __('messages.legal.terms_art7_body') }}</p>
            <ul>
                <li>{{ __('messages.legal.terms_art7_item1') }}</li>
                <li>{{ __('messages.legal.terms_art7_item2') }}</li>
                <li>{{ __('messages.legal.terms_art7_item3') }}</li>
                <li>{{ __('messages.legal.terms_art7_item4') }}</li>
            </ul>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art8_title') }}</h2>
            <p>{{ __('messages.legal.terms_art8_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art9_title') }}</h2>
            <p>{{ __('messages.legal.terms_art9_body') }}</p>
        </article>

        <article class="legal-article">
            <h2>{{ __('messages.legal.terms_art10_title') }}</h2>
            <p>{{ __('messages.legal.terms_art10_body') }}</p>
        </article>
    </div>
</section>
@endsection
