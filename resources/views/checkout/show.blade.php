<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.checkout.badge') }} | Afrik Appart</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-600">{{ __('messages.checkout.badge') }}</p>
            <h1 class="text-3xl font-bold">{{ __('messages.checkout.title') }}</h1>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p class="text-sm text-slate-500">{{ __('messages.checkout.reference') }}</p>
                        <h2 class="text-xl font-semibold">{{ $reservation->reservation_ref }}</h2>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $reservation->status }}</span>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.checkout.property') }}</dt>
                        <dd class="mt-1 font-medium">{{ $reservation->property?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Dates</dt>
                        <dd class="mt-1">{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Montant</dt>
                        <dd class="mt-1 font-semibold">{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} {{ $reservation->currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.checkout.client') }}</dt>
                        <dd class="mt-1">{{ $reservation->guest?->full_name ?? $reservation->email }}</dd>
                    </div>
                </dl>

                <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-lg font-semibold">{{ __('messages.checkout.methods') }}</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                        @foreach ($paymentMethods as $provider => $method)
                            <li>• {{ __('messages.checkout.' . $provider) }}@if ($method['instructions']) — {{ $method['instructions'] }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <form action="{{ route('checkout.start', $reservation) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="provider" class="mb-1 block text-sm font-medium text-slate-700">{{ __('messages.checkout.mode') }}</label>
                        <select id="provider" name="provider" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none">
                            @foreach ($paymentMethods as $provider => $method)
                                <option value="{{ $provider }}">{{ __('messages.checkout.' . $provider) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-amber-500 px-4 py-2.5 font-medium text-white hover:bg-amber-600">
                        {{ __('messages.checkout.start') }}
                    </button>
                </form>

                @if($reservation->paymentAttempts->isNotEmpty())
                    <div class="mt-6 border-t border-slate-200 pt-4">
                        <h3 class="text-md font-semibold">{{ __('messages.checkout.attempts') }}</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach($reservation->paymentAttempts as $attempt)
                                <li class="rounded-md bg-slate-50 px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">{{ $attempt->provider }}</span>
                                        <span class="text-slate-500">{{ $attempt->status }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $attempt->provider_reference }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('checkout.complete', $reservation) }}" method="POST" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-md border border-slate-300 bg-white px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">
                        {{ __('messages.checkout.simulate') }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</body>
</html>
