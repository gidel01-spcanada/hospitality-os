<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'seed' => [
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'guest_password' => env('SEED_GUEST_PASSWORD'),
    ],

    'payments' => [
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],

    'recaptcha' => [
        'enabled' => filled(env('RECAPTCHA_SITE_KEY')) && filled(env('RECAPTCHA_SECRET_KEY')),
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'minimum_score' => (float) env('RECAPTCHA_MINIMUM_SCORE', 0.5),
    ],

    'property_copy' => [
        'provider' => env('PROPERTY_COPY_PROVIDER', 'groq'),
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('PROPERTY_COPY_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('PROPERTY_COPY_MODEL', 'openai/gpt-oss-20b'),
        'daily_limit' => (int) env('PROPERTY_COPY_DAILY_LIMIT', 10),
    ],

    'analytics' => [
        'enabled' => (bool) env('ANALYTICS_ENABLED', false),
        'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
        'gtm_container_id' => env('GTM_CONTAINER_ID'),
    ],

    'fedapay' => [
        'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
        'public_key' => env('FEDAPAY_PUBLIC_KEY'),
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        'base_url' => env('FEDAPAY_BASE_URL', 'https://api.fedapay.com/v1'),
    ],

    'paypal' => [
        'environment' => env('PAYPAL_ENVIRONMENT', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.paypal.com'),
    ],

    'cinetpay' => [
        'environment' => env('CINETPAY_ENVIRONMENT', 'sandbox'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'api_key' => env('CINETPAY_API_KEY'),
        'base_url' => env('CINETPAY_BASE_URL', 'https://api-checkout.cinetpay.com/v2'),
    ],

    'mpesa' => [
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'base_url' => env('MPESA_BASE_URL', 'https://api.safaricom.co.ke'),
    ],

];
