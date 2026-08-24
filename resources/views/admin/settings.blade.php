@extends('layouts.app')

@section('title', __('messages.admin.settings_title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-gold">{{ __('messages.admin.title') }}</span>
            <h1>{{ __('messages.admin.settings_title') }}</h1>
            <p>{{ __('messages.admin.settings_description') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card full-width">
            @if (session('status'))
                <div class="alert success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf

                <div class="form-grid">
                    <div>
                        <label for="site_name">{{ __('messages.admin.site_name') }}</label>
                        <input id="site_name" name="site_name" type="text" value="{{ old('site_name', $brand['site_name'] ?? 'Afrik Appart') }}">
                    </div>

                    <div>
                        <label for="site_tagline">{{ __('messages.admin.tagline') }}</label>
                        <input id="site_tagline" name="site_tagline" type="text" value="{{ old('site_tagline', $brand['site_tagline'] ?? 'Séjours premium pour des escapades sereines en Afrique de l’Ouest.') }}">
                    </div>

                    <div>
                        <label for="contact_email">{{ __('messages.admin.contact_email') }}</label>
                        <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $brand['contact_email'] ?? 'support@afrikappart.example') }}">
                    </div>

                    <div>
                        <label for="support_phone">{{ __('messages.admin.support_phone') }}</label>
                        <input id="support_phone" name="support_phone" type="text" value="{{ old('support_phone', $brand['support_phone'] ?? '+229 00 00 00 00') }}">
                    </div>

                    <div>
                        <label for="default_locale">{{ __('messages.admin.default_language') }}</label>
                        <select id="default_locale" name="default_locale">
                            <option value="fr" @selected(old('default_locale', $brand['default_locale'] ?? 'fr') === 'fr')>Français</option>
                            <option value="en" @selected(old('default_locale', $brand['default_locale'] ?? 'fr') === 'en')>English</option>
                        </select>
                    </div>

                    <div>
                        <label for="secondary_locale">{{ __('messages.admin.secondary_language') }}</label>
                        <select id="secondary_locale" name="secondary_locale">
                            <option value="fr" @selected(old('secondary_locale', $brand['secondary_locale'] ?? 'en') === 'fr')>Français</option>
                            <option value="en" @selected(old('secondary_locale', $brand['secondary_locale'] ?? 'en') === 'en')>English</option>
                        </select>
                    </div>

                    <div>
                        <label for="review_source_booking_url">{{ __('messages.admin.booking_reviews_url') }}</label>
                        <input id="review_source_booking_url" name="review_source_booking_url" type="url" value="{{ old('review_source_booking_url', $brand['review_source_booking_url'] ?? '') }}" placeholder="https://www.booking.com/...">
                    </div>

                    <div>
                        <label for="review_source_google_url">{{ __('messages.admin.google_reviews_url') }}</label>
                        <input id="review_source_google_url" name="review_source_google_url" type="url" value="{{ old('review_source_google_url', $brand['review_source_google_url'] ?? '') }}" placeholder="https://maps.google.com/...">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_settings') }}</button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">Retour</a>
                </div>
            </form>
        </div>
    </section>
@endsection
