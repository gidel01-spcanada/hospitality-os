@extends('layouts.app')

@section('title', __('messages.establishments.title', ['brand' => \App\Support\PlatformBrand::name()]))
@section('seo_description', __('messages.establishments.subtitle'))

@section('content')
    <section class="property-show-header establishment-directory-header">
        <div class="container">
            <div class="property-show-topline">
                <a href="{{ route('home') }}" class="inline-link">← {{ __('messages.nav.home') }}</a>
                <span class="badge badge-emerald">{{ __('messages.establishments.badge') }}</span>
            </div>
            <div class="property-title-block">
                <h1>{{ __('messages.establishments.heading') }}</h1>
                <p>{{ __('messages.establishments.subtitle') }}</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container establishment-directory-grid">
            @forelse ($establishments as $establishment)
                @php
                    $imagePath = $establishment->publicImagePaths()[0] ?? null;
                @endphp
                <a class="establishment-directory-card" href="{{ route('establishments.show', $establishment) }}">
                    @if ($imagePath)<img src="{{ asset($imagePath) }}" alt="{{ $establishment->localized('name') }}" loading="lazy">@endif
                    <div class="establishment-directory-copy">
                        <span class="badge badge-emerald">{{ __('messages.establishments.property_count', ['count' => $establishment->properties_count]) }}</span>
                        <h2>{{ $establishment->localized('name') }}</h2>
                        @if ($establishment->city || $establishment->country_code)
                            <p>{{ implode(', ', array_filter([$establishment->city, $establishment->country_code])) }}</p>
                        @endif
                        @if ($establishment->localized('description'))
                            <p>{{ \Illuminate\Support\Str::limit($establishment->localized('description'), 160) }}</p>
                        @endif
                        <span class="inline-link">{{ __('messages.establishments.view') }} →</span>
                    </div>
                </a>
            @empty
                <p class="empty-state">{{ __('messages.establishments.none') }}</p>
            @endforelse
        </div>
    </section>
@endsection