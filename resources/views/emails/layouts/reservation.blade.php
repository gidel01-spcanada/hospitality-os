<!DOCTYPE html>
@php
    $emailBrand = \App\Support\BrandSettings::all();
    $emailBrandName = \App\Support\PlatformBrand::name();
    $emailLogo = config('platform.mode') !== 'cloud' && filled($emailBrand['site_favicon_url'] ?? null) ? asset($emailBrand['site_favicon_url']) : null;
    $emailIcon = config('platform.mode') === 'cloud' ? \Illuminate\Support\Str::substr($emailBrandName, 0, 1) : ($emailBrand['site_icon'] ?? 'A');
    $emailRecipientName = $recipient_name ?? ($reservation_summary['guest_name'] ?? null);
    $emailFirstName = $emailRecipientName && ! filter_var($emailRecipientName, FILTER_VALIDATE_EMAIL) ? \Illuminate\Support\Str::before(trim($emailRecipientName), ' ') : null;
    $emailLocale = app()->getLocale();
    $emailContactEmail = filter_var($emailBrand['contact_email'] ?? null, FILTER_VALIDATE_EMAIL) && ! str_ends_with(strtolower($emailBrand['contact_email']), '.example') ? $emailBrand['contact_email'] : null;
    $emailSupportPhone = filled($emailBrand['support_phone'] ?? null) && $emailBrand['support_phone'] !== \App\Support\BrandSettings::DEFAULTS['support_phone'] ? $emailBrand['support_phone'] : null;
    $emailLink = 'color:#0f5b4c;text-decoration:none;font-weight:bold;';
@endphp
<html lang="{{ str_replace('_', '-', $emailLocale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject ?? $emailBrandName }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-shell { padding: 0 !important; }
            .email-body, .email-hero, .email-header, .email-footer { padding-left: 18px !important; padding-right: 18px !important; }
            .email-card { padding: 16px !important; }
            .email-title { font-size: 22px !important; }
            .email-col { display: block !important; width: 100% !important; padding: 0 0 12px !important; }
            .email-media-image { width: 100% !important; max-width: 100% !important; height: auto !important; }
            .email-button { display: table !important; width: 100% !important; margin: 6px 0 !important; }
            .email-button a { display: block !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef2f0;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#1d2a2d;line-height:1.55;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('email-preheader')</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f0;">
        <tr><td class="email-shell" align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #dce4e0;">
                <tr><td class="email-header" style="padding:20px 32px;background:#0f5b4c;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="52" style="padding-right:12px;vertical-align:middle;">
                                @if ($emailLogo)<img src="{{ $emailLogo }}" alt="{{ $emailBrandName }}" width="40" height="40" style="display:block;border-radius:8px;background:#ffffff;">@else<span style="display:inline-block;width:40px;height:40px;line-height:40px;text-align:center;background:#c9a24a;color:#ffffff;font-size:20px;font-weight:bold;border-radius:8px;">{{ $emailIcon }}</span>@endif
                            </td>
                            <td style="vertical-align:middle;">
                                <a href="{{ route('home') }}" style="font-size:20px;font-weight:bold;color:#ffffff;text-decoration:none;letter-spacing:0.01em;">{{ $emailBrandName }}</a>
                                <p style="margin:2px 0 0;color:#cfe5dd;font-size:12px;">{{ __('messages.transactional.platform_role', ['brand' => $emailBrandName]) }}</p>
                            </td>
                        </tr>
                    </table>
                </td></tr>
                @hasSection('email-title')
                    <tr><td class="email-hero" style="padding:28px 32px 4px;">
                        @hasSection('email-eyebrow')<p style="margin:0 0 6px;font-size:12px;font-weight:bold;letter-spacing:0.1em;text-transform:uppercase;color:#a07d2c;">@yield('email-eyebrow')</p>@endif
                        <h1 class="email-title" style="margin:0;font-size:26px;line-height:1.25;color:#0a3f35;">@yield('email-title')</h1>
                    </td></tr>
                @endif
                <tr><td class="email-body" style="padding:20px 32px 28px;font-size:15px;">
                    <p style="margin:0 0 14px;font-size:16px;">{{ $emailFirstName ? __('messages.transactional.greeting', ['name' => $emailFirstName]) : __('messages.transactional.greeting_generic') }}</p>
                    @yield('email-content')
                    <p style="margin:24px 0 0;font-size:14px;color:#5b6b70;">{{ __('messages.transactional.closing_help') }}</p>
                    <p style="margin:6px 0 0;font-size:15px;font-weight:bold;color:#0a3f35;">{{ __('messages.transactional.signature', ['brand' => $emailBrandName]) }}</p>
                </td></tr>
                <tr><td class="email-footer" style="padding:24px 32px;background:#f7f9f8;border-top:1px solid #e3e8e5;font-size:12px;color:#5b6b70;line-height:1.6;">
                    <p style="margin:0 0 4px;font-size:14px;font-weight:bold;color:#0a3f35;">{{ $emailBrandName }}</p>
                    <p style="margin:0 0 12px;">{{ __('messages.transactional.footer_managed', ['brand' => $emailBrandName]) }}</p>
                    <p style="margin:0 0 12px;">
                        <a href="{{ route('home') }}" style="{{ $emailLink }}">{{ __('messages.transactional.footer_website') }}</a> &nbsp;·&nbsp;
                        <a href="{{ route('contact', ['lang' => $emailLocale]) }}" style="{{ $emailLink }}">{{ __('messages.transactional.footer_support') }}</a> &nbsp;·&nbsp;
                        <a href="{{ route('privacy', ['lang' => $emailLocale]) }}" style="{{ $emailLink }}">{{ __('messages.legal.privacy') }}</a> &nbsp;·&nbsp;
                        <a href="{{ route('terms', ['lang' => $emailLocale]) }}" style="{{ $emailLink }}">{{ __('messages.legal.terms') }}</a>
                    </p>
                    @if ($emailContactEmail || $emailSupportPhone)
                        <p style="margin:0 0 12px;">
                            @if ($emailContactEmail)<a href="mailto:{{ $emailContactEmail }}" style="color:#0f5b4c;">{{ $emailContactEmail }}</a>@endif
                            @if ($emailContactEmail && $emailSupportPhone) &nbsp;·&nbsp; @endif
                            @if ($emailSupportPhone){{ $emailSupportPhone }}@endif
                        </p>
                    @endif
                    <p style="margin:0 0 8px;">{{ __('messages.transactional.via_platform', ['brand' => $emailBrandName]) }}</p>
                    <p style="margin:0 0 8px;">{{ __('messages.transactional.footer_security', ['brand' => $emailBrandName]) }}</p>
                    <p style="margin:0;">&copy; {{ now()->year }} {{ $emailBrandName }}. {{ __('messages.transactional.footer_rights') }}</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>