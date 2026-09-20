@extends('layouts.app')

@section('title', __('messages.common.contact'))
@section('seo_description', __('messages.seo.contact_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
@php($brand = \App\Support\BrandSettings::all())
<section class="page-hero compact-hero">
    <div class="container">
        <span class="badge badge-emerald">Support</span>
        <h1>{{ __('messages.contact.title') }}</h1>
        <p>{{ __('messages.contact.subtitle') }}</p>
    </div>
</section>

<section class="container dashboard-grid">
    <div class="summary-card">
        <h2>{{ __('messages.contact.email') }}</h2>
        <p><a href="mailto:{{ $brand['contact_email'] }}">{{ $brand['contact_email'] }}</a></p>
    </div>
    <div class="summary-card">
        <h2>{{ __('messages.contact.phone') }}</h2>
        <p><a href="tel:{{ preg_replace('/[^+\d]/', '', $brand['support_phone']) }}">{{ $brand['support_phone'] }}</a></p>
    </div>
    <div class="summary-card full-width">
        <h2>{{ __('messages.contact.hours') }}</h2>
        <p>{{ __('messages.contact.hours_value') }}</p>
    </div>
</section>
@endsection
