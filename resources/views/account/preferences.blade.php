@extends('layouts.app')

@section('title', __('messages.profile.tabs.preferences'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.dashboard.account_type') }}</span>
            <h1>{{ __('messages.profile.tabs.preferences') }}</h1>
            <p>{{ __('messages.profile.subtitle') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            @if (session('status'))
                <div class="alert success">
                    {{ session('status') }}
                </div>
            @endif

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
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost">{{ __('messages.profile.back') }}</a>
                </div>
            </form>
        </div>
    </section>
@endsection
