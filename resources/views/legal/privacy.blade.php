@extends('layouts.app')

@section('title', __('messages.legal.privacy'))
@section('seo_description', __('messages.seo.privacy_description'))

@section('content')
<section class="page-hero compact-hero">
    <div class="container narrow">
        <span class="badge badge-emerald">{{ __('messages.legal.title') }}</span>
        <h1>{{ __('messages.legal.privacy') }}</h1>
    </div>
</section>

<section class="container auth-panel">
    <div class="summary-card full-width">
        <p>{{ __('messages.legal.privacy_one') }}</p>
        <p>{{ __('messages.legal.privacy_two') }}</p>
    </div>
</section>
@endsection
