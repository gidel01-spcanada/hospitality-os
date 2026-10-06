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

            <div class="property-editor-tabs settings-editor-tabs" data-property-tabs>
                <div class="property-tab-list" role="tablist" aria-label="{{ __('messages.admin.settings_sections') }}">
                    @foreach (['general' => __('messages.admin.general_information'), 'social' => __('messages.admin.social_networks'), 'email' => __('messages.admin.email_communications')] as $tab => $label)
                        <button type="button" class="property-tab {{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="settings-panel-{{ $tab }}" data-property-tab="{{ $tab }}">{{ $label }}</button>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf

                    <div id="settings-panel-general" class="property-tab-panel settings-tab-panel is-active" role="tabpanel" data-property-panel="general">
                        <div class="form-grid">
                    <div>
                        <label for="site_name">{{ __('messages.admin.site_name') }}</label>
                        <input id="site_name" name="site_name" type="text" value="{{ old('site_name', $brand['site_name'] ?? 'Afrik Appart') }}">
                    </div>

                    <div>
                        <label for="site_icon">{{ __('messages.admin.site_icon') }}</label>
                        <input id="site_icon" name="site_icon" type="text" maxlength="3" value="{{ old('site_icon', $brand['site_icon'] ?? 'A') }}">
                    </div>

                    <label class="checkbox-row" for="site_icon_only">
                        <input id="site_icon_only" name="site_icon_only" type="checkbox" value="1" @checked(old('site_icon_only', $brand['site_icon_only'] ?? '0') === '1')>
                        <span>{{ __('messages.admin.site_icon_only') }}</span>
                    </label>

                    <div>
                        <label for="site_favicon_url">{{ __('messages.admin.site_favicon_url') }}</label>
                        <input id="site_favicon_url" name="site_favicon_url" type="url" value="{{ old('site_favicon_url', $brand['site_favicon_url'] ?? '') }}" placeholder="https://">
                    </div>

                    <label class="checkbox-row" for="clear_site_favicon">
                        <input id="clear_site_favicon" name="clear_site_favicon" type="checkbox" value="1" @checked(old('clear_site_favicon'))>
                        <span>{{ __('messages.admin.clear_site_favicon') }}</span>
                    </label>

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
                        <input id="site_tagline" name="site_tagline" type="text" value="{{ old('site_tagline', $brand['site_tagline'] ?? __('messages.brand.default_tagline')) }}">
                    </div>

                    <div>
                        <label for="homepage_background_image">{{ __('messages.admin.homepage_background_image') }}</label>
                        <input id="homepage_background_image" name="homepage_background_image" type="url" value="{{ old('homepage_background_image', $brand['homepage_background_image'] ?? '') }}" placeholder="https://">
                    </div>

                    <label class="checkbox-row" for="restore_homepage_background_image">
                        <input id="restore_homepage_background_image" name="restore_homepage_background_image" type="checkbox" value="1" @checked(old('restore_homepage_background_image'))>
                        <span>{{ __('messages.admin.restore_homepage_background_image') }}</span>
                    </label>

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
                    </div>

                    <div id="settings-panel-social" class="property-tab-panel settings-tab-panel" role="tabpanel" data-property-panel="social" hidden>
                        <h2 style="margin-bottom: var(--space-4);">{{ __('messages.admin.social_networks') }}</h2>
                        <p class="form-help">{{ __('messages.admin.social_networks_help') }}</p>
                        <div class="form-grid">
                            @foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin', 'whatsapp'] as $network)
                                <div>
                                    <label for="social_{{ $network }}_url">{{ __('messages.admin.social_' . $network) }}</label>
                                    <input id="social_{{ $network }}_url" name="social_{{ $network }}_url" type="url" value="{{ old('social_' . $network . '_url', $brand['social_' . $network . '_url'] ?? '') }}" placeholder="https://">
                                    @error('social_' . $network . '_url')<span class="form-error">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="settings-panel-email" class="property-tab-panel settings-tab-panel" role="tabpanel" data-property-panel="email" hidden>
                        <h2 style="margin-bottom: var(--space-4);">{{ __('messages.admin.email_communications') }}</h2>
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
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_settings') }}</button>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">Retour</a>
                    </div>
                </form>
            </div>
    </x-card>
@endsection
