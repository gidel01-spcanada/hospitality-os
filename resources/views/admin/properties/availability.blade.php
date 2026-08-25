@extends('layouts.admin')

@section('title', __('messages.admin.availability_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $property->name }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.availability_description') }}</p>
    </div>

    <x-card>
        <div class="card-body">
            <h2>{{ __('messages.admin.result') }}</h2>
            <p>
                <strong>{{ $available ? __('messages.admin.available') : __('messages.admin.unavailable') }}</strong>
                {{ __('messages.admin.from_to', ['from' => $checkIn->format('d/m/Y'), 'to' => $checkOut->format('d/m/Y')]) }}
            </p>
            <div class="form-actions">
                <a class="btn btn-ghost" href="{{ route('admin.properties.edit', $property) }}">{{ __('messages.admin.back_property') }}</a>
            </div>
        </div>
    </x-card>
@endsection
