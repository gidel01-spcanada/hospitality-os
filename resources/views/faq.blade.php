@extends('layouts.app')

@section('title', __('messages.faq.title'))
@section('seo_description', __('messages.seo.faq_description', ['brand' => \App\Support\PlatformBrand::name()]))

@section('content')
<section class="page-hero compact-hero">
    <div class="container">
        <span class="badge badge-emerald">{{ __('messages.faq.badge') }}</span>
        <h1>{{ __('messages.faq.title') }}</h1>
        <p>{{ __('messages.faq.subtitle') }}</p>
    </div>
</section>

<section class="container page-content">
    <div class="summary-card full-width faq-list">
        @foreach (['booking', 'payment', 'cancellation', 'checkin', 'amenities', 'support'] as $topic)
            <details class="faq-item">
                <summary>{{ __('messages.faq.' . $topic . '_question') }}</summary>
                <p>{{ __('messages.faq.' . $topic . '_answer') }}</p>
            </details>
        @endforeach

        <p class="faq-contact">
            {{ __('messages.faq.more_help') }}
            <a href="{{ route('contact') }}">{{ __('messages.faq.contact_us') }}</a>
        </p>
    </div>
</section>
@endsection
