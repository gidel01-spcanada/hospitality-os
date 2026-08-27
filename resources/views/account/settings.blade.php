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

            <div class="summary-card" style="margin-bottom: 1.5rem;">
                <h2>{{ __('messages.profile.tabs.preferences') }}</h2>
                <form method="POST" action="{{ route('dashboard.preferences.update') }}">
                    @csrf
                    <div>
                        <label for="locale">{{ __('messages.profile.language') }}</label>
                        <select id="locale" name="locale">
                            <option value="fr" @selected(old('locale', $user->locale ?? 'fr') === 'fr')>Français</option>
                            <option value="en" @selected(old('locale', $user->locale ?? 'fr') === 'en')>English</option>
                        </select>
                    </div>
                    <div>
                        <label><input type="checkbox" name="email_booking_updates" value="1" @checked(old('email_booking_updates', (bool) ($user->email_booking_updates ?? true)))> {{ __('messages.profile.booking_updates') }}</label>
                        <label><input type="checkbox" name="email_message_updates" value="1" @checked(old('email_message_updates', (bool) ($user->email_message_updates ?? true)))> {{ __('messages.profile.message_updates') }}</label>
                        <label><input type="checkbox" name="email_marketing" value="1" @checked(old('email_marketing', (bool) ($user->email_marketing ?? false)))> {{ __('messages.profile.marketing') }}</label>
                        <label><input type="checkbox" name="email_newsletter" value="1" @checked(old('email_newsletter', (bool) ($user->email_newsletter ?? false)))> {{ __('messages.profile.newsletter') }}</label>
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
