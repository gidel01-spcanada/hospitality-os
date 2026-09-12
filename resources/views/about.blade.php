@extends('layouts.app')

@php
    $isCloud = config('platform.mode') === 'cloud';
    $brandName = \App\Support\PlatformBrand::name();
    $modeSuffix = $isCloud ? 'cloud' : 'on_premise';
@endphp

@section('title', __('messages.about.title_' . $modeSuffix, ['brand' => $brandName]))
@section('seo_description', __('messages.seo.about_description', ['brand' => $brandName]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container">
        <span class="badge badge-emerald">{{ __('messages.about.badge') }}</span>
        <h1>{{ __('messages.about.title_' . $modeSuffix, ['brand' => $brandName]) }}</h1>
        <p>{{ __('messages.about.subtitle_' . $modeSuffix) }}</p>
    </div>
</section>

<section class="container page-content">
    <div class="summary-card full-width legal-document">
        <article class="legal-article">
            <h2>{{ __('messages.about.story_title_' . $modeSuffix) }}</h2>
            <p>{{ __('messages.about.story_body1_' . $modeSuffix, ['brand' => $brandName]) }}</p>
            <p>{{ __('messages.about.story_body2_' . $modeSuffix, ['brand' => $brandName]) }}</p>
        </article>
    </div>

    <div class="dashboard-grid" style="margin-top: 1.5rem;">
        <div class="summary-card">
            <h2>{{ __('messages.about.pill1_title_' . $modeSuffix) }}</h2>
            <p>{{ __('messages.about.pill1_text_' . $modeSuffix) }}</p>
        </div>
        <div class="summary-card">
            <h2>{{ __('messages.about.pill2_title_' . $modeSuffix) }}</h2>
            <p>{{ __('messages.about.pill2_text_' . $modeSuffix) }}</p>
        </div>
        <div class="summary-card full-width">
            <h2>{{ __('messages.about.pill3_title_' . $modeSuffix) }}</h2>
            <p>{{ __('messages.about.pill3_text_' . $modeSuffix) }}</p>
        </div>
    </div>
</section>
@endsection
