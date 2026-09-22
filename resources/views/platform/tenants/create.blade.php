@extends('layouts.admin')

@section('title', __('messages.platform.add_tenant'))

@section('content')
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.platform.add_tenant') }}</h1>
    <p class="admin-page-description">{{ __('messages.platform.add_tenant_description') }}</p>
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

    <form method="POST" action="{{ route('platform.tenants.store') }}">
        @csrf

        <x-form-group title="{{ __('messages.platform.tenant_details') }}">
            <x-input name="name" label="{{ __('messages.platform.tenant_name') }}" required value="{{ old('name') }}" />
            <x-input name="slug" label="{{ __('messages.platform.tenant_slug') }}" value="{{ old('slug') }}" />
            <x-input name="contact_email" label="{{ __('messages.platform.tenant_contact_email') }}" type="email" value="{{ old('contact_email') }}" />
        </x-form-group>

        <x-form-group title="{{ __('messages.platform.initial_admin') }}" description="{{ __('messages.platform.initial_admin_help') }}">
            <x-input name="admin_name" label="{{ __('messages.platform.initial_admin_name') }}" required value="{{ old('admin_name') }}" />
            <x-input name="admin_email" label="{{ __('messages.platform.initial_admin_email') }}" type="email" required value="{{ old('admin_email') }}" />
        </x-form-group>

        <div class="form-actions">
            <x-button type="submit" variant="primary">{{ __('messages.platform.add_tenant') }}</x-button>
            <x-button tag="a" href="{{ route('platform.tenants.index') }}" variant="secondary">{{ __('messages.admin.cancel') }}</x-button>
        </div>
    </form>
</x-card>
@endsection
