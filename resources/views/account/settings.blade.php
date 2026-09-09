@extends('layouts.app')

@section('title', __('messages.profile.tabs.settings'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.dashboard.account_type') }}</span>
            <h1>{{ __('messages.profile.tabs.settings') }}</h1>
            <p>{{ __('messages.profile.subtitle') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            <div class="summary-card" style="margin-bottom: 1.5rem;">
                <h2>{{ __('messages.profile.personal') }}</h2>
                <form method="POST" action="{{ route('account.profile.update') }}">
                    @csrf
                    <div>
                        <label for="name">{{ __('messages.profile.full_name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.profile.save') }}</button>
                    </div>
                </form>
            </div>

            <div class="summary-card">
                <h2>{{ __('messages.security.title') }}</h2>
                <form method="POST" action="{{ route('account.security.update') }}">
                    @csrf
                    <div>
                        <label for="current_password_settings">{{ __('messages.security.current_password') }}</label>
                        <input id="current_password_settings" name="current_password" type="password" required>
                    </div>
                    <div>
                        <label for="password_settings">{{ __('messages.security.new_password') }}</label>
                        <input id="password_settings" name="password" type="password" required>
                    </div>
                    <div>
                        <label for="password_confirmation_settings">{{ __('messages.security.confirm_password') }}</label>
                        <input id="password_confirmation_settings" name="password_confirmation" type="password" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.security.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
