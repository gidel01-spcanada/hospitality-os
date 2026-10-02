@extends('layouts.app')

@section('title', __('messages.checkout.identity_title'))

@section('content')
    <section class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <span class="badge badge-emerald">{{ __('messages.checkout.badge') }}</span>
                <h1>{{ __('messages.checkout.identity_title') }}</h1>
                <p>{{ __('messages.checkout.identity_description') }}</p>
            </div>

            @if (session('status'))
                <div class="form-alert form-alert-success" role="status">{{ session('status') }}</div>
            @endif

            <p class="form-help">{{ $reservation->reservation_ref }} · {{ $reservation->email }}</p>
            <div class="auth-form" style="display: flex; flex-direction: column; gap: var(--space-3);">
                <form method="POST" action="{{ route('checkout.password-setup', $reservation) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button class="btn btn-primary auth-submit" type="submit">{{ __('messages.checkout.set_password') }}</button>
                </form>
                <form method="POST" action="{{ route('checkout.skip-password-setup', $reservation) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button class="btn btn-ghost auth-submit" type="submit">{{ __('messages.checkout.skip_password_setup') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection