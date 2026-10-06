@props(['properties', 'filters' => []])

@if ($properties->count() >= 2)
    <form id="compare-form" method="GET" action="{{ route('properties.compare') }}" class="compare-toolbar">
        <strong>{{ __('messages.properties.compare_select') }}</strong>
        <span data-compare-count>{{ __('messages.properties.compare_selected', ['count' => 0]) }}</span>
        <small>{{ __('messages.properties.compare_limit') }}</small>
        @if (($filters['check_in'] ?? null) && ($filters['check_out'] ?? null))
            <input type="hidden" name="check_in" value="{{ $filters['check_in'] }}">
            <input type="hidden" name="check_out" value="{{ $filters['check_out'] }}">
            <input type="hidden" name="guests" value="{{ $filters['guests'] ?? 1 }}">
        @endif
        <button class="btn btn-ghost" type="submit" disabled>{{ __('messages.properties.compare_action') }}</button>
    </form>
@endif