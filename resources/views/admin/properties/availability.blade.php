@extends('layouts.app')

@section('title', __('messages.admin.availability_title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container narrow-shell">
            <span class="badge badge-emerald">{{ __('messages.admin.availability_title') }}</span>
            <h1>{{ $property->name }}</h1>
            <p>{{ __('messages.admin.availability_description') }}</p>
        </div>
    </section>

    <section class="container admin-form-shell">
        <div class="admin-panel">
            <h2>{{ __('messages.admin.result') }}</h2>
            <p>
                <strong>{{ $available ? __('messages.admin.available') : __('messages.admin.unavailable') }}</strong>
                {{ __('messages.admin.from_to', ['from' => $checkIn->format('d/m/Y'), 'to' => $checkOut->format('d/m/Y')]) }}
            </p>
            <div class="form-actions">
                <a class="btn btn-ghost" href="{{ route('admin.properties.edit', $property) }}">{{ __('messages.admin.back_property') }}</a>
            </div>
        </div>
    </section>
@endsection
