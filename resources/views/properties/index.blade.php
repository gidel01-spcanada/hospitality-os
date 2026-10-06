@extends('layouts.app')

@section('title', __('messages.properties.title', ['brand' => \App\Support\PlatformBrand::name()]))
@section('seo_description', __('messages.seo.properties_description'))

@section('content')
    <section class="page-hero">
        <div class="container narrow-shell">
            <span class="eyebrow eyebrow-dark">{{ __('messages.properties.badge') }}</span>
            <h1>{{ __('messages.properties.heading') }}</h1>
            <p>{{ __('messages.properties.subtitle') }}</p>
            <x-share-panel class="share-panel-inline" :title="__('messages.properties.heading') . ' | ' . \App\Support\PlatformBrand::name()" :label="__('messages.properties.share_search')" state="[data-share-search-form]" />
        </div>
    </section>

    <section class="section">
        <div class="container">
            <x-property-filters
                variant="catalog"
                :action="route('properties.index')"
                :filters="$filters"
                :destinations="$destinations"
                :establishments="$establishments"
                :amenities="$amenities"
                :filter-currency="$filterCurrency"
                :price-ceiling="$priceCeiling"
                :results-count="$properties->count()"
                :is-customer="auth()->user()?->role === 'customer'"
            />
            <x-property-comparison :properties="$properties" :filters="$filters" />
            <div class="property-grid property-grid-large">
                @forelse ($properties as $property)
                    <x-property-card :property="$property" :filters="$filters" :favorite-property-ids="$favoritePropertyIds" :compare-enabled="$properties->count() >= 2" />
                @empty
                    <div class="empty-state">
                        <p>{{ __('messages.properties.none') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
