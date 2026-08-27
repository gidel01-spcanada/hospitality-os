@extends('layouts.admin')

@section('title', __('messages.admin.settings_title'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ __('messages.admin.settings_title') }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.settings_description') }}</p>
    </div>

    <x-card>
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
                        <label for="site_icon">{{ __('messages.admin.site_icon') }}</label>
                        <input id="site_icon" name="site_icon" type="text" maxlength="3" value="{{ old('site_icon', $brand['site_icon'] ?? 'A') }}">
                    </div>

                    @if (config('platform.mode') === 'on_premise')
                        <div>
                            <label for="customer_theme">{{ __('messages.admin.customer_theme') }}</label>
                            <select id="customer_theme" name="customer_theme">
                                @foreach (['emerald-gold' => __('messages.admin.theme_emerald_gold'), 'ocean-coral' => __('messages.admin.theme_ocean_coral'), 'terracotta-teal' => __('messages.admin.theme_terracotta_teal'), 'sunrise-ink' => __('messages.admin.theme_sunrise_ink')] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('customer_theme', $brand['customer_theme'] ?? 'emerald-gold') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="site_tagline">{{ __('messages.admin.tagline') }}</label>
                        <input id="site_tagline" name="site_tagline" type="text" value="{{ old('site_tagline', $brand['site_tagline'] ?? 'Séjours premium pour des escapades sereines en Afrique de l’Ouest.') }}">
                    </div>

                    <div>
                        <label for="footer_copyright">{{ __('messages.admin.footer_copyright') }}</label>
                        <input id="footer_copyright" name="footer_copyright" type="text" value="{{ old('footer_copyright', $brand['footer_copyright'] ?? '') }}">
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

                <div style="margin-top: var(--space-8);">
                    <h2 style="margin-bottom: var(--space-4);">{{ __('messages.admin.customer_emails') }}</h2>
                    <div class="form-grid">
                        <div>
                            <label for="email_sender_name">{{ __('messages.admin.email_sender_name') }}</label>
                            <input id="email_sender_name" name="email_sender_name" type="text" value="{{ old('email_sender_name', $brand['email_sender_name'] ?? 'Afrik Appart') }}">
                        </div>

                        <div>
                            <label for="email_sender_email">{{ __('messages.admin.email_sender_email') }}</label>
                            <input id="email_sender_email" name="email_sender_email" type="email" value="{{ old('email_sender_email', $brand['email_sender_email'] ?? 'support@afrikappart.example') }}">
                        </div>

                        <div>
                            <label for="guest_account_setup_subject">{{ __('messages.admin.guest_account_setup_subject') }}</label>
                            <input id="guest_account_setup_subject" name="guest_account_setup_subject" type="text" value="{{ old('guest_account_setup_subject', $brand['guest_account_setup_subject'] ?? '') }}">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="guest_account_setup_message">{{ __('messages.admin.guest_account_setup_message') }}</label>
                            <textarea id="guest_account_setup_message" name="guest_account_setup_message" rows="4">{{ old('guest_account_setup_message', $brand['guest_account_setup_message'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="customer_confirmation_subject">{{ __('messages.admin.customer_confirmation_subject') }}</label>
                            <input id="customer_confirmation_subject" name="customer_confirmation_subject" type="text" value="{{ old('customer_confirmation_subject', $brand['customer_confirmation_subject'] ?? '') }}">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="customer_confirmation_message">{{ __('messages.admin.customer_confirmation_message') }}</label>
                            <textarea id="customer_confirmation_message" name="customer_confirmation_message" rows="4">{{ old('customer_confirmation_message', $brand['customer_confirmation_message'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="pre_arrival_subject">{{ __('messages.admin.pre_arrival_subject') }}</label>
                            <input id="pre_arrival_subject" name="pre_arrival_subject" type="text" value="{{ old('pre_arrival_subject', $brand['pre_arrival_subject'] ?? '') }}">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="pre_arrival_message">{{ __('messages.admin.pre_arrival_message') }}</label>
                            <textarea id="pre_arrival_message" name="pre_arrival_message" rows="4">{{ old('pre_arrival_message', $brand['pre_arrival_message'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="post_stay_subject">{{ __('messages.admin.post_stay_subject') }}</label>
                            <input id="post_stay_subject" name="post_stay_subject" type="text" value="{{ old('post_stay_subject', $brand['post_stay_subject'] ?? '') }}">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="post_stay_message">{{ __('messages.admin.post_stay_message') }}</label>
                            <textarea id="post_stay_message" name="post_stay_message" rows="4">{{ old('post_stay_message', $brand['post_stay_message'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_settings') }}</button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">Retour</a>
                </div>
            </form>
    </x-card>
@endsection
