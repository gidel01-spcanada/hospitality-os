@extends('layouts.app')

@section('title', __('messages.properties.compare_title'))

@section('content')
<section class="page-hero">
    <div class="container narrow-shell">
        <span class="eyebrow eyebrow-dark">{{ __('messages.properties.compare_badge') }}</span>
        <h1>{{ __('messages.properties.compare_title') }}</h1>
        <p>{{ __('messages.properties.compare_description') }}</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="compare-share-actions">
            <button type="button" class="btn btn-ghost" data-copy-text="{{ url()->full() }}" data-copy-success="{{ __('messages.properties.compare_link_copied') }}" data-copy-error="{{ __('messages.properties.compare_link_copy_failed') }}">{{ __('messages.properties.compare_copy_link') }}</button>
            <span class="compare-copy-status" data-copy-text-status role="status" aria-live="polite" hidden></span>
            <a class="btn btn-ghost" href="https://wa.me/?text={{ urlencode(__('messages.properties.compare_whatsapp', ['url' => url()->full()])) }}" target="_blank" rel="noopener">{{ __('messages.properties.compare_whatsapp_action') }}</a>
        </div>
        @if(request('check_in') && request('check_out'))
            <div class="compare-context" role="note">
                <strong>{{ __('messages.properties.compare_context') }}</strong>
                <span>{{ request('check_in') }} → {{ request('check_out') }}</span>
                @if(request('guests'))<span> · {{ trans_choice('messages.properties.compare_guests', (int) request('guests'), ['count' => (int) request('guests')]) }}</span>@endif
            </div>
        @endif
        <div class="compare-summary-grid compare-summary-grid-{{ $properties->count() }}">
            @foreach ($properties as $property)
                <article class="compare-summary-card">
                    <h2>{{ $property->localized('name') }}</h2>
                    <p>{{ $property->city ?: __('messages.properties.location_on_request') }}</p>
                    <strong>{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }} / {{ __('messages.properties.night_short') }}</strong>
                    <a class="compare-property-action" href="{{ route('properties.show', $property) }}">{{ __('messages.properties.view_details') }} →</a>
                </article>
            @endforeach
        </div>
        <div class="compare-table-wrap" tabindex="0" aria-label="{{ __('messages.properties.compare_title') }}">
            <table class="compare-table compare-table-{{ $properties->count() }}">
                <thead>
                    <tr>
                        <th scope="col">{{ __('messages.properties.compare_criteria') }}</th>
                        @foreach ($properties as $property)
                            <th scope="col">
                                <a href="{{ route('properties.show', $property) }}">{{ $property->localized('name') }}</a>
                                <small>{{ $property->city ?: __('messages.properties.location_on_request') }}</small>
                                <a class="compare-property-action" href="{{ route('properties.show', $property) }}">{{ __('messages.properties.view_details') }} →</a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr><th scope="row">{{ __('messages.properties.compare_price') }}</th>@foreach($properties as $property)<td>@if(isset($cardPricing[$property->id]))<strong>{{ number_format($cardPricing[$property->id]['total_amount'], 0, ',', ' ') }} {{ $cardPricing[$property->id]['currency'] }}</strong> {{ __('messages.properties.for_nights', ['nights' => $cardPricing[$property->id]['nights']]) }}<small class="compare-secondary-price">{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }} / {{ __('messages.properties.night_short') }}</small>@else<strong>{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }}</strong> / {{ __('messages.properties.night_short') }}@endif</td>@endforeach</tr>
                        <tr><th scope="row">{{ __('messages.properties.compare_price') }}</th>@foreach($properties as $property)<td>@if(isset($cardPricing[$property->id]))<strong>{{ number_format($cardPricing[$property->id]['total_amount'], 0, ',', ' ') }} {{ $cardPricing[$property->id]['currency'] }}</strong> {{ __('messages.properties.for_nights', ['nights' => $cardPricing[$property->id]['nights']]) }}<small class="compare-secondary-price">{{ __('messages.properties.taxes_fees_included') }} · {{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }} / {{ __('messages.properties.night_short') }}</small>@else<strong>{{ number_format($property->nightly_rate_xof, 0, ',', ' ') }} {{ $property->currency }}</strong> / {{ __('messages.properties.night_short') }}@endif</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.guests') }}</th>@foreach($properties as $property)<td>{{ $property->max_guests }}</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.bedrooms') }}</th>@foreach($properties as $property)<td>{{ $property->bedrooms }}</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.beds') }}</th>@foreach($properties as $property)<td>{{ $property->beds }}</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.admin.bathrooms') }}</th>@foreach($properties as $property)<td>{{ $property->bathrooms }}</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.area') }}</th>@foreach($properties as $property)<td>{{ $property->area ? rtrim(rtrim(number_format((float) $property->area, 2, ',', ' '), '0'), ',') . ' ' . ($property->area_unit ?: 'm²') : '—' }}</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.compare_rating') }}</th>@foreach($properties as $property)@php $hasPropertyReviews = $property->reviews->isNotEmpty(); $reviews = $hasPropertyReviews ? $property->reviews : ($property->establishment?->reviews ?? collect()); @endphp<td>{{ $reviews->isNotEmpty() ? '★ '.number_format((float) $reviews->avg('rating'), 1, ',', ' ').' · '.trans_choice('messages.properties.review_count_short', $reviews->count(), ['count' => $reviews->count()]) : __('messages.properties.new_listing') }}@if($reviews->isNotEmpty())<small class="compare-secondary-price">{{ $hasPropertyReviews ? __('messages.properties.rating_scope_property') : __('messages.properties.rating_scope_establishment') }}</small>@endif</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.compare_amenities') }}</th>@foreach($properties as $property)<td>{{ $property->amenities->pluck('name')->take(4)->implode(' · ') ?: '—' }}</td>@endforeach</tr>
                    <tr><th scope="row">{{ __('messages.properties.compare_cancellation') }}</th>@foreach($properties as $property)<td>{{ (float) ($property->establishment?->cancellation_fee_percent ?? 0) <= 0 ? __('messages.properties.flexible_cancellation') : __('messages.properties.compare_policy_configured') }}</td>@endforeach</tr>
                </tbody>
            </table>
        </div>
        <div class="form-actions compare-actions"><a class="btn btn-primary" href="{{ route('properties.index') }}">{{ __('messages.properties.back_to_properties') }}</a></div>
    </div>
</section>
@endsection
