@extends('layouts.admin')

@section('title', __('messages.admin.add_host'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.add_host') }}</h1>
    <p class="admin-page-description">{{ $establishment->name }}</p>
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

    <form method="POST" action="{{ route('admin.establishments.hosts.store', $establishment) }}">
        @csrf
        <label class="full-width">
            <span>{{ __('messages.admin.host_mode') }}</span>
            <select name="mode" data-host-mode>
                <option value="create">{{ __('messages.admin.host_mode_create') }}</option>
                @if (auth()->user()->isAdmin())
                    <option value="existing">{{ __('messages.admin.host_mode_existing') }}</option>
                @endif
            </select>
        </label>
        @if (auth()->user()->isAdmin())
            <label class="full-width" data-host-existing-field hidden>
                <span>{{ __('messages.admin.existing_user') }}</span>
                <select name="existing_user_id">
                    <option value="">{{ __('messages.admin.select_user') }}</option>
                    @foreach ($availableHostUsers as $availableHostUser)
                        <option value="{{ $availableHostUser->id }}">{{ $availableHostUser->name }} — {{ $availableHostUser->email }} ({{ $availableHostUser->role }})</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div data-host-create-fields>
            <div class="admin-form-grid">
                <label>
                    <span>{{ __('messages.admin.host_name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </label>
                <label>
                    <span>{{ __('messages.common.email') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label>
                    <span>{{ __('messages.admin.initial_password') }}</span>
                    <input type="password" name="password" required>
                </label>
                <label>
                    <span>{{ __('messages.common.confirm_password') }}</span>
                    <input type="password" name="password_confirmation" required>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.admin.add_host') }}</x-button>
            <x-button tag="a" href="{{ route('admin.establishments.edit', $establishment) . '#hosts' }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
