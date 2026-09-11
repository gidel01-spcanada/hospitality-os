@php
    $brand = \App\Support\BrandSettings::all();
    // Cloud mode is one unified brand across every tenant's properties -- never a tenant's own site name.
    $brand['site_name'] = \App\Support\PlatformBrand::name();
    $siteIcon = $brand['site_icon'] ?? 'A';
    $customerTheme = config('platform.mode') === 'on_premise' ? ($brand['customer_theme'] ?? 'emerald-gold') : 'emerald-gold';
    $currentLocale = app()->getLocale();
    $availableLocales = ['fr', 'en'];
    $alternateLocale = collect($availableLocales)->first(fn (string $locale) => $locale !== $currentLocale);
    $isPrivatePage = request()->routeIs('admin.*', 'account.*', 'dashboard', 'dashboard.reservations.*', 'messages.*', 'checkout.*');
    $seoDescription = trim((string) $__env->yieldContent('seo_description', __('messages.seo.site_description', ['brand' => $brand['site_name']])));
    $seoTitle = trim((string) $__env->yieldContent('title', config('app.name', $brand['site_name'] ?? __('messages.brand.default_name'))));
    $seoImage = trim((string) $__env->yieldContent('seo_image', asset('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80')));
    $footerCopyright = strtr((string) ($brand['footer_copyright'] ?? ''), [':year' => now()->year, ':site_name' => $brand['site_name']]);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-site-theme="{{ $customerTheme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', $brand['site_name'] ?? __('messages.brand.default_name')))</title>
        <meta name="description" content="{{ $seoDescription }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta name="robots" content="{{ $isPrivatePage ? 'noindex,nofollow' : 'index,follow' }}">
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
    </head>
    <body class="app-shell">
        <header class="topbar">
            <div class="container topbar-inner">
                <a href="{{ route('home') }}" class="brand" aria-label="{{ $brand['site_name'] ?? __('messages.brand.default_name') }} homepage">
                    <span class="brand-mark">{{ $siteIcon }}</span>
                    <span>{{ $brand['site_name'] ?? __('messages.brand.default_name') }}</span>
                </a>
                <nav class="nav" aria-label="Main navigation">
                    <a href="{{ route('home') }}">{{ __('messages.nav.home') }}</a>
                    <a href="{{ route('properties.index') }}">{{ __('messages.nav.properties') }}</a>
                    <a href="{{ route('home') }}#experience">{{ __('messages.nav.experience') }}</a>
                    <a href="#contact">{{ __('messages.nav.contact') }}</a>
                </nav>
                <button type="button" class="mobile-menu-toggle" data-mobile-menu-toggle aria-expanded="false" aria-controls="mobile-nav-actions" aria-label="Menu">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </button>
                <div class="nav-actions">
                    @guest
                        @if ($alternateLocale)
                            <div class="locale-switch" aria-label="Language switcher">
                                <a href="{{ route('language.switch', ['locale' => $alternateLocale]) }}" class="active">
                                    {{ strtoupper($alternateLocale) }}
                                </a>
                            </div>
                        @endif
                    @endguest
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

        <main>
            @yield('content')
        </main>

        <footer class="site-footer" id="contact">
            <div class="container footer-inner">
                <div>
                    <div class="brand footer-brand">
                        <span class="brand-mark">{{ $siteIcon }}</span>
                        <span>{{ $brand['site_name'] ?? __('messages.brand.default_name') }}</span>
                    </div>
                    <p>{{ $brand['site_tagline'] ?? __('messages.brand.default_tagline') }}</p>
                </div>
                <div>
                    <h3>{{ __('messages.brand.contact') }}</h3>
                    <p>{{ $brand['contact_email'] ?? 'support@afrikappart.example' }}</p>
                    <p>{{ $brand['support_phone'] ?? '+229 00 00 00 00' }}</p>
                    <p><a href="{{ route('faq') }}">{{ __('messages.faq.title') }}</a></p>
                </div>
                <div>
                    <h3>{{ __('messages.legal.title') }}</h3>
                    <p><a href="{{ route('privacy') }}">{{ __('messages.legal.privacy') }}</a></p>
                    <p><a href="{{ route('terms') }}">{{ __('messages.legal.terms') }}</a></p>
                    <p><a href="{{ route('cookies') }}">{{ __('messages.legal.cookies') }}</a></p>
                </div>
            </div>
            <div class="container footer-copyright">
                <p>{{ $footerCopyright }}</p>
            </div>
        </footer>
    </body>
</html>
