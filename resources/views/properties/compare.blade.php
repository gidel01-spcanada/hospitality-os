@extends('layouts.app')
@section('title', __('messages.properties.compare_title'))

@section('content')
<section class="section comparison-page" data-comparison-page data-endpoint="{{ route('properties.compare') }}" data-error="{{ __('messages.comparison.refresh_error') }}" data-loading="{{ __('messages.comparison.loading') }}" data-ready="{{ __('messages.comparison.ready') }}" data-invalid-dates="{{ __('messages.comparison.invalid_dates') }}" data-copied="{{ __('messages.properties.compare_link_copied') }}" data-copy-error="{{ __('messages.properties.compare_link_copy_failed') }}">
    <div class="container">
        <header class="comparison-heading">
            <div><a class="inline-link" href="{{ route('properties.index') }}" data-comparison-back>{{ __('messages.properties.back_to_properties') }}</a><h1>{{ __('messages.properties.compare_title') }}</h1></div>
            <button type="button" class="btn btn-ghost" data-comparison-copy>{{ __('messages.properties.compare_copy_link') }}</button>
        </header>
        <form class="comparison-controls" data-comparison-controls method="GET" action="{{ route('properties.compare') }}">
            <div data-comparison-selected>
                @foreach ($properties as $property)<input type="hidden" name="properties[]" value="{{ $property->id }}">@endforeach
            </div>
            <label>{{ __('messages.properties.check_in') }}<input type="date" name="check_in" min="{{ today()->toDateString() }}" value="{{ $criteria['check_in'] ?? '' }}"></label>
            <label>{{ __('messages.properties.check_out') }}<input type="date" name="check_out" min="{{ today()->addDay()->toDateString() }}" value="{{ $criteria['check_out'] ?? '' }}"></label>
            <label>{{ __('messages.properties.guests') }}<input type="number" name="guests" min="1" max="100" value="{{ $criteria['guests'] ?? 1 }}"></label>
            <label>{{ __('messages.comparison.currency') }}<select name="currency">@foreach ($currencies as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach</select></label>
            <button class="btn btn-primary" type="submit">{{ __('messages.comparison.update') }}</button>
        </form>
        <div class="comparison-tools">
            <fieldset class="comparison-mode" @if ($properties->count() < 2) hidden @endif><legend class="sr-only">{{ __('messages.comparison.display') }}</legend>
                <label><input type="radio" name="comparison_mode" value="differences" checked>{{ __('messages.comparison.differences') }}</label>
                <label><input type="radio" name="comparison_mode" value="all">{{ __('messages.comparison.all') }}</label>
            </fieldset>
            <div class="comparison-add">
                <label for="comparison-add-property" class="sr-only">{{ __('messages.comparison.add') }}</label>
                <select id="comparison-add-property" data-comparison-add><option value="">{{ __('messages.comparison.add') }}</option>
                    @foreach ($candidates as $candidate)<option value="{{ $candidate->id }}" @disabled($properties->contains('id', $candidate->id))>{{ $candidate->localized('name') }}</option>@endforeach
                </select>
                <button type="button" class="btn btn-ghost" data-comparison-add-button>{{ __('messages.comparison.add_action') }}</button>
            </div>
        </div>
        <p class="comparison-limit">{{ __('messages.comparison.limit') }}</p>
        <p class="comparison-legend"><span class="comparison-legend-mark" aria-hidden="true"></span>{{ __('messages.comparison.legend') }}</p>
        <p role="status" aria-live="polite" class="comparison-feedback" data-comparison-feedback></p>
        <div data-comparison-content>
            @include('properties.partials.comparison-table')
        </div>
    </div>
</section>
@endsection
