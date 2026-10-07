@php
    $brand = \App\Support\BrandSettings::all();
    // Cloud mode is one unified brand across every tenant's properties -- never a tenant's own site name.
    $brand['site_name'] = \App\Support\PlatformBrand::name();
    $siteIcon = $brand['site_icon'] ?? 'A';
    $siteIconOnly = filter_var($brand['site_icon_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $siteIconImage = $siteIconOnly && filled($brand['site_favicon_url'] ?? null) ? $brand['site_favicon_url'] : null;
    $siteHeaderImage = $siteIconImage;
    $customerTheme = config('platform.mode') === 'on_premise' ? ($brand['customer_theme'] ?? 'emerald-gold') : 'emerald-gold';
    $currentLocale = app()->getLocale();
    $availableLocales = ['fr', 'en'];
    $alternateLocale = collect($availableLocales)->first(fn (string $locale) => $locale !== $currentLocale);
    $isPrivatePage = request()->routeIs('admin.*', 'account.*', 'dashboard', 'dashboard.reservations.*', 'messages.*', 'checkout.*');
    $seoDescription = trim((string) $__env->yieldContent('seo_description', __('messages.seo.site_description', ['brand' => $brand['site_name']])));
    $seoTitle = trim((string) $__env->yieldContent('title', config('app.name', $brand['site_name'] ?? __('messages.brand.default_name'))));
    $seoImage = trim((string) $__env->yieldContent('seo_image', asset('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80')));
    $footerCopyrightTemplate = (string) ($brand['footer_copyright'] ?? '');
    if ($footerCopyrightTemplate === \App\Support\BrandSettings::DEFAULTS['footer_copyright']) {
        $footerCopyrightTemplate = __('messages.brand.footer_copyright');
    }
    $footerCopyright = strtr($footerCopyrightTemplate, [':year' => now()->year, ':site_name' => $brand['site_name']]);
    $footerEmail = filled($brand['contact_email'] ?? null) && $brand['contact_email'] !== \App\Support\BrandSettings::DEFAULTS['contact_email'] ? $brand['contact_email'] : null;
    $footerPhone = filled($brand['support_phone'] ?? null) && $brand['support_phone'] !== \App\Support\BrandSettings::DEFAULTS['support_phone'] ? $brand['support_phone'] : null;
    $isPortalPage = auth()->check()
        && ! auth()->user()->isAdmin()
        && request()->routeIs('dashboard', 'dashboard.reservations.*', 'account.*', 'messages.*', 'admin.*');
    $isPortalStaff = $isPortalPage && request()->routeIs('admin.*');
    $analyticsConfig = [
        'ga4MeasurementId' => config('services.analytics.ga4_measurement_id'),
        'gtmContainerId' => config('services.analytics.gtm_container_id'),
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-site-theme="{{ $customerTheme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ $brand['site_favicon_url'] ?: asset('favicon.ico') }}">
        <title>@yield('title', config('app.name', $brand['site_name'] ?? __('messages.brand.default_name')))</title>
        <meta name="description" content="{{ $seoDescription }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta name="robots" content="{{ $isPrivatePage ? 'noindex,nofollow' : 'index,follow' }}">
        <script>window.__ANALYTICS_CONFIG__ = @json(config('services.analytics.enabled') && ! $isPrivatePage ? $analyticsConfig : null);</script>
        @unless ($isPrivatePage)
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ $brand['site_name'] ?? __('messages.brand.default_name') }}">
            <meta property="og:locale" content="{{ app()->getLocale() === 'fr' ? 'fr_FR' : 'en_US' }}">
            <meta property="og:title" content="{{ $seoTitle }}">
            <meta property="og:description" content="{{ $seoDescription }}">
            <meta property="og:image" content="{{ $seoImage }}">
            <meta property="og:url" content="{{ url()->current() }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $seoTitle }}">
            <meta name="twitter:description" content="{{ $seoDescription }}">
            <meta name="twitter:image" content="{{ $seoImage }}">
        @endunless
        @stack('head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="app-shell {{ $isPortalPage ? 'has-account-portal' : '' }}">
        <a class="skip-link" href="#main-content">{{ __('messages.common.skip_to_content') }}</a>
        @if ($isPortalPage)
            <header class="portal-topbar">
                <div class="container portal-topbar-inner">
                    <button type="button" class="portal-menu-toggle" data-portal-nav-toggle aria-expanded="false" aria-controls="portal-navigation" aria-label="{{ __('messages.nav.mobile_menu') }}">
                        <span class="portal-menu-toggle-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                    </button>
                    <a href="{{ route('home') }}" class="brand portal-brand" aria-label="{{ $brand['site_name'] }}">
                        @if ($siteHeaderImage)<img class="brand-mark brand-mark-image" src="{{ $siteHeaderImage }}" alt="" width="46" height="46">@else<span class="brand-mark">{{ $siteIcon }}</span>@endif
                        <span>{{ $brand['site_name'] }}</span>
                    </a>
                    <div class="portal-topbar-actions">
                        <a class="portal-public-link" href="{{ route('home') }}">{{ __('messages.nav.home') }}</a>
                        <details class="portal-user-menu">
                            <summary>{{ auth()->user()->name }}</summary>
                            <nav aria-label="{{ __('messages.nav.account') }}">
                                <a href="{{ route('account.profile') }}">{{ __('messages.profile.tabs.profile') }}</a>
                                <a href="{{ route('account.preferences') }}">{{ __('messages.profile.tabs.preferences') }}</a>
                                <a href="{{ route('account.security') }}">{{ __('messages.profile.tabs.security') }}</a>
                                <a href="{{ route('contact') }}">{{ __('messages.footer.need_help') }}</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit">{{ __('messages.nav.logout') }}</button>
                                </form>
                            </nav>
                        </details>
                    </div>
                </div>
            </header>
            <div class="container portal-workspace">
                @include('partials.portal-navigation')
                <main id="main-content" class="portal-main" tabindex="-1">
                    @if ($message = session('success') ?? session('status'))
                        <div class="alert success" role="status" aria-live="polite">{{ $message }}</div>
                    @endif
                    @if ($message = session('error'))
                        <div class="alert error" role="alert" aria-live="assertive">{{ $message }}</div>
                    @endif
                    @if ($isPortalStaff && ($breadcrumbs ?? null))
                        <nav class="portal-breadcrumbs" aria-label="Breadcrumb">
                            <a href="{{ route('dashboard') }}">{{ __('messages.nav.home') }}</a>
                            @foreach ($breadcrumbs as $crumb)
                                <span aria-hidden="true">›</span>
                                @if ($loop->last)<span aria-current="page">{{ $crumb['title'] }}</span>@else<a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>@endif
                            @endforeach
                        </nav>
                    @endif
                    @yield('content')
                </main>
            </div>
        @else
        <header class="topbar">
            <div class="container topbar-inner">
                <a href="{{ route('home') }}" class="brand {{ $siteHeaderImage ? 'brand-has-image' : '' }}" aria-label="{{ $brand['site_name'] ?? __('messages.brand.default_name') }} homepage">
                    @if ($siteHeaderImage)<img class="brand-mark brand-mark-image" src="{{ $siteHeaderImage }}" alt="" width="46" height="46">@else<span class="brand-mark">{{ $siteIcon }}</span>@endif
                    <span>{{ $brand['site_name'] ?? __('messages.brand.default_name') }}</span>
                </a>
                <nav class="nav" aria-label="{{ __('messages.nav.mobile_navigation') }}">
                    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('messages.nav.home') }}</a>
                    <a href="{{ route('properties.index') }}" class="nav-primary-link {{ request()->routeIs('properties.*', 'establishments.*') ? 'is-active' : '' }}" @if (request()->routeIs('properties.*', 'establishments.*')) aria-current="page" @endif>{{ __('messages.nav.properties') }}</a>
                    <a href="{{ route('home') }}#experience">{{ __('messages.nav.experience') }}</a>
                    <a href="#contact">{{ __('messages.nav.contact') }}</a>
                </nav>
                <button type="button" class="mobile-menu-toggle" data-mobile-menu-toggle aria-expanded="false" aria-controls="mobile-nav-actions" aria-label="{{ __('messages.nav.mobile_menu') }}">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </button>
                <div class="nav-actions">
                    <nav class="mobile-nav-links" aria-label="{{ __('messages.nav.mobile_navigation') }}">
                        <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('messages.nav.home') }}</a>
                        <a href="{{ route('properties.index') }}" @if (request()->routeIs('properties.*', 'establishments.*')) aria-current="page" @endif>{{ __('messages.nav.properties') }}</a>
                        <a href="{{ route('home') }}#experience">{{ __('messages.nav.experience') }}</a>
                        <a href="{{ route('home') }}#contact">{{ __('messages.nav.contact') }}</a>
                    </nav>
                    @if ($alternateLocale)
                        <div class="locale-switch" aria-label="{{ __('messages.nav.language_switcher') }}">
                            <a href="{{ route('language.switch', ['locale' => $alternateLocale]) }}" lang="{{ $alternateLocale }}" hreflang="{{ $alternateLocale }}">
                                {{ strtoupper($alternateLocale) }}
                            </a>
                        </div>
                    @endif
                    @auth
                        @if (auth()->user()->canManageReservations())
                            <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">{{ __('messages.nav.admin') }}</a>
                        @endif
                        <a class="btn btn-ghost" href="{{ route('account.profile') }}">{{ __('messages.nav.account') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ __('messages.nav.logout') }}</button>
                        </form>
                    @else
                        @if (config('platform.mode') === 'cloud')
                            <a class="btn btn-ghost" href="{{ route('host.register') }}">{{ __('messages.auth.host_register_badge') }}</a>
                        @endif
                        <a class="btn btn-ghost" href="{{ route('login') }}">{{ __('messages.nav.login') }}</a>
                        <a class="btn btn-primary" href="{{ route('register') }}">{{ __('messages.nav.sign_up') }}</a>
                    @endauth
                </div>
            </div>
        </header>

        @auth
            @if (auth()->user()->canManageReservations() && request()->routeIs('admin.*'))
                <div class="container section-tabs-shell">
                    @include('partials.admin-tabs')
                </div>
            @elseif (request()->routeIs('account.*', 'dashboard', 'dashboard.reservations.*', 'messages.*'))
                <div class="container section-tabs-shell">
                    @include('partials.account-tabs')
                </div>
            @endif
        @endauth

        <main id="main-content" tabindex="-1">
            @yield('content')
        </main>
        @endif

        <footer class="site-footer" id="contact">
            <div class="container footer-inner">
                <section class="footer-column footer-brand-column">
                    <a href="{{ route('home') }}" class="brand footer-brand {{ $siteHeaderImage ? 'brand-has-image' : '' }}" aria-label="{{ $brand['site_name'] ?? __('messages.brand.default_name') }} homepage">
                        @if ($siteHeaderImage)<img class="brand-mark brand-mark-image" src="{{ $siteHeaderImage }}" alt="" width="46" height="46">@else<span class="brand-mark">{{ $siteIcon }}</span>@endif
                        <span>{{ $brand['site_name'] ?? __('messages.brand.default_name') }}</span>
                    </a>
                    <p>{{ $brand['site_tagline'] ?? __('messages.brand.default_tagline') }}</p>
                </section>
                <section class="footer-column">
                    <h3>{{ __('messages.footer.discover') }}</h3>
                    <nav class="footer-link-list" aria-label="{{ __('messages.footer.discover') }}">
                        <a href="{{ route('properties.index') }}">{{ __('messages.nav.properties') }}</a>
                    </nav>
                </section>
                <section class="footer-column">
                    <h3>{{ __('messages.footer.contact_support') }}</h3>
                    <nav class="footer-link-list" aria-label="{{ __('messages.footer.contact_support') }}">
                        <a href="{{ route('contact') }}" class="footer-help-link">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5 8 8 0 0 1-3.4-.76L4 20l1.25-4.1A7.5 7.5 0 1 1 20 11.5Z" /></svg>
                            {{ __('messages.footer.need_help') }}
                        </a>
                        @if ($footerEmail)<a href="mailto:{{ $footerEmail }}">{{ $footerEmail }}</a>@endif
                        @if ($footerPhone)<a href="tel:{{ preg_replace('/[^+\d]/', '', $footerPhone) }}">{{ $footerPhone }}</a>@endif
                        <a href="{{ route('faq') }}">{{ __('messages.faq.title') }}</a>
                        <a href="{{ route('about') }}">{{ __('messages.about.badge') }}</a>
                    </nav>
                    @php
                        $socialNetworks = ['facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin', 'whatsapp'];
                        $activeSocialNetworks = collect($socialNetworks)
                            ->filter(fn ($network) => filled($brand['social_' . $network . '_url'] ?? null));
                    @endphp
                    @if ($activeSocialNetworks->isNotEmpty())
                        <nav class="footer-social-links" aria-label="{{ __('messages.admin.social_networks') }}">
                            @foreach ($activeSocialNetworks as $network)
                                @php($socialLabel = __('messages.admin.social_' . $network))
                                <a href="{{ $brand['social_' . $network . '_url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $socialLabel }}" title="{{ $socialLabel }}">
                                    <x-social-icon :network="$network" />
                                </a>
                            @endforeach
                        </nav>
                    @endif
                </section>
                <section class="footer-column">
                    <h3>{{ __('messages.legal.title') }}</h3>
                    <nav class="footer-link-list" aria-label="{{ __('messages.legal.title') }}">
                        <a href="{{ route('privacy') }}">{{ __('messages.legal.privacy') }}</a>
                        <a href="{{ route('terms') }}">{{ __('messages.legal.terms') }}</a>
                        <a href="{{ route('cookies') }}">{{ __('messages.legal.cookies') }}</a>
                    </nav>
                </section>
            </div>
            <div class="footer-copyright">
                <div class="container footer-copyright-content">
                    <p>{{ $footerCopyright }}</p>
                    <p class="footer-powered-by">{{ __('messages.brand.powered_by') }}</p>
                    <a class="footer-feedback-link" href="{{ route('feedback.create') }}">{{ __('messages.feedback.link') }}</a>
                </div>
            </div>
        </footer>
        @if (filled(config('services.help_chat.api_key')))
            @include('partials.help-chat')
        @endif
        @unless ($isPrivatePage)
            @include('partials.analytics-consent')
        @endunless
        @include('partials.confirm-dialog')
        @stack('scripts')
    </body>
</html>
