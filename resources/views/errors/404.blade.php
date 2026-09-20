<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.errors.404_title') }} | {{ \App\Support\PlatformBrand::name() }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="app-shell error-page">
    <main class="error-page-shell">
        <div class="error-card">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark">A</span><span>{{ \App\Support\PlatformBrand::name() }}</span></a>
            <p class="eyebrow">{{ __('messages.errors.code', ['code' => 404]) }}</p>
            <h1>{{ __('messages.errors.404_title') }}</h1>
            <p>{{ __('messages.errors.404_message') }}</p>
            <a href="{{ route('home') }}" class="btn btn-primary">{{ __('messages.errors.back_home') }}</a>
        </div>
    </main>
</body>
</html>
