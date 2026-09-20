@if (config('services.analytics.enabled') && (config('services.analytics.ga4_measurement_id') || config('services.analytics.gtm_container_id')))
    <div class="analytics-consent" data-analytics-consent hidden role="dialog" aria-live="polite" aria-label="{{ __('messages.analytics.consent_title') }}">
        <p><strong>{{ __('messages.analytics.consent_title') }}</strong> {{ __('messages.analytics.consent_message') }}</p>
        <div class="analytics-consent-actions">
            <button type="button" class="btn btn-primary" data-analytics-consent-accept>{{ __('messages.analytics.accept') }}</button>
            <button type="button" class="btn btn-ghost" data-analytics-consent-decline>{{ __('messages.analytics.decline') }}</button>
        </div>
    </div>
@endif
