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
                <div class="alert success" role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('dashboard.preferences.update') }}" class="portal-form" data-portal-form>
                @csrf

                <fieldset class="portal-preference-group">
                    <legend>{{ __('messages.profile.language') }}</legend>
                    <p class="portal-form-help">{{ __('messages.profile.language_preference_help') }}</p>
                    <label for="locale">{{ __('messages.profile.language') }}</label>
                    <select id="locale" name="locale" required>
                        <option value="fr" @selected(old('locale', $user->locale ?? 'fr') === 'fr')>Français</option>
                        <option value="en" @selected(old('locale', $user->locale ?? 'fr') === 'en')>English</option>
                    </select>
                    @error('locale')<p class="portal-field-error" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="portal-preference-group">
                    <legend>{{ __('messages.profile.communication_preferences') }}</legend>
                    <p class="portal-form-help">{{ __('messages.profile.communication_preferences_help') }}</p>
                    <label class="portal-toggle-row"><input type="checkbox" name="email_booking_updates" value="1" @checked(old('email_booking_updates', (bool) ($user->email_booking_updates ?? true)))> <span>{{ __('messages.profile.booking_updates') }}</span></label>
                    <label class="portal-toggle-row"><input type="checkbox" name="email_message_updates" value="1" @checked(old('email_message_updates', (bool) ($user->email_message_updates ?? true)))> <span>{{ __('messages.profile.message_updates') }}</span></label>
                    <label class="portal-toggle-row"><input type="checkbox" name="email_marketing" value="1" @checked(old('email_marketing', (bool) ($user->email_marketing ?? false)))> <span>{{ __('messages.profile.marketing') }}</span></label>
                    <label class="portal-toggle-row"><input type="checkbox" name="email_newsletter" value="1" @checked(old('email_newsletter', (bool) ($user->email_newsletter ?? false)))> <span>{{ __('messages.profile.newsletter') }}</span></label>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.profile.save') }}</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost">{{ __('messages.profile.back') }}</a>
                </div>
            </form>
        </div>
    </section>
@endsection
