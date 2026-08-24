@extends('layouts.app')

@section('title', __('messages.security.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.dashboard.account_type') }}</span>
            <h1>{{ __('messages.security.title') }}</h1>
            <p>{{ __('messages.security.subtitle') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            @if (session('status'))
                <div class="alert success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('account.security.update') }}">
                @csrf

                <div>
                    <label for="current_password">{{ __('messages.security.current_password') }}</label>
                    <input id="current_password" name="current_password" type="password" required>
                </div>

                <div>
                    <label for="password">{{ __('messages.security.new_password') }}</label>
                    <input id="password" name="password" type="password" required>
                </div>

                <div>
                    <label for="password_confirmation">{{ __('messages.security.confirm_password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.security.save') }}</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost">{{ __('messages.profile.back') }}</a>
                </div>
            </form>
        </div>
    </section>
@endsection
