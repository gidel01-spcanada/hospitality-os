<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trop de demandes | {{ \App\Support\PlatformBrand::name() }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-600">Erreur 429</p>
            <h1 class="mt-4 text-4xl font-bold">{{ __('messages.errors.429_title') }}</h1>
            <p class="mt-4 text-slate-600">{{ __('messages.errors.429_message') }}</p>
            <a href="{{ route('home') }}" class="mt-6 inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">{{ __('messages.errors.back_home') }}</a>
        </div>
    </div>
</body>
</html>
