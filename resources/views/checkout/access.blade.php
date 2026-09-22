@extends('layouts.app')

@section('title', __('messages.checkout.access_title'))

@section('content')
    <section class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <span class="badge badge-emerald">{{ __('messages.checkout.access_badge') }}</span>
                <h1>{{ __('messages.checkout.access_title') }}</h1>
                <p>{{ __('messages.checkout.access_intro') }}</p>
            </div>

            <div class="auth-form" style="display: flex; flex-direction: column; gap: var(--space-3);">
                <a class="btn btn-primary auth-submit" href="{{ route('login') }}">{{ __('messages.auth.sign_in') }}</a>
                <a class="btn btn-ghost auth-submit" href="{{ route('register') }}">{{ __('messages.auth.create_account') }}</a>
            </div>

            <p class="auth-switch">{{ __('messages.checkout.access_help') }}</p>
        </div>
    </section>
@endsection
