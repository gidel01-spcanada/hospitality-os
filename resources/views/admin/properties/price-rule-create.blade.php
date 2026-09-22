@extends('layouts.admin')

@section('title', __('messages.admin.add_rule'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.add_rule') }}</h1>
    <p class="admin-page-description">{{ $property->name }}</p>
</div>

<x-card>
    @if ($errors->any())
        <div class="form-alert form-alert-error" role="alert" tabindex="-1">
            <strong>{{ __('messages.common.error') }}</strong>
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.properties.price-rules.store', $property) }}">
        @csrf
        <div class="admin-form-grid">
            <label><span>{{ __('messages.admin.start') }}</span><input type="date" name="effective_from" value="{{ old('effective_from') }}" required></label>
            <label><span>{{ __('messages.admin.end') }}</span><input type="date" name="effective_to" value="{{ old('effective_to') }}"></label>
            <label><span>{{ __('messages.admin.rate_xof') }}</span><input type="number" step="0.01" name="nightly_rate_xof" value="{{ old('nightly_rate_xof', $property->nightly_rate_xof) }}" required></label>
            <label><span>{{ __('messages.admin.minimum_stay') }}</span><input type="number" name="minimum_stay" min="1" value="{{ old('minimum_stay', $property->minimum_stay) }}" required></label>
            <label><span>{{ __('messages.admin.rule_type') }}</span><input name="rule_type" value="{{ old('rule_type', 'standard') }}" required></label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.add_rule') }}</x-button>
            <x-button tag="a" href="{{ route('admin.properties.edit', $property) . '#rules' }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
