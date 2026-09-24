@extends('layouts.admin')

@section('title', __('messages.admin.add_feature'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.add_feature') }}</h1>
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

    <form method="POST" action="{{ route('admin.properties.features.store', $property) }}">
        @csrf
        <input type="hidden" name="active_tab" value="features">
        <div class="admin-form-grid">
            <label class="full-width"><span>{{ __('messages.admin.feature_name') }}</span><input name="name" value="{{ old('name') }}" placeholder="Private pool, concierge service..." required></label>
            <label class="full-width"><span>{{ __('messages.admin.description') }}</span><textarea name="description" rows="3">{{ old('description') }}</textarea></label>
            <label><span>{{ __('messages.admin.cost_xof') }}</span><input type="number" step="0.01" min="0" name="cost_xof" value="{{ old('cost_xof', 0) }}" required></label>
            <label class="checkbox-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))><span>{{ __('messages.admin.active') }}</span></label>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.add_feature') }}</x-button>
            <x-button tag="a" href="{{ route('admin.properties.edit', $property) . '#features' }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
