<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.checkout.badge') }} | {{ \App\Support\PlatformBrand::name() }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-600">{{ __('messages.checkout.badge') }}</p>
            <h1 class="text-3xl font-bold">{{ __('messages.checkout.title') }}</h1>
            @auth
                <a class="mt-2 inline-block text-sm text-amber-700 underline" href="{{ route('dashboard') }}">{{ __('messages.checkout.back_to_account') }}</a>
            @endauth
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
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ __('messages.admin.status_' . $reservation->status) }}</span>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.checkout.property') }}</dt>
                        <dd class="mt-1 font-medium">{{ $reservation->property?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.reservation.dates') }}</dt>
                        <dd class="mt-1">{{ $reservation->check_in?->format('d/m/Y') }} → {{ $reservation->check_out?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.reservation.total') }}</dt>
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
                @if ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed')
                    <form action="{{ route('checkout.start', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="space-y-4">
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
                @endif

                @if (in_array('pay_later', array_keys($paymentMethods), true) && $reservation->status === 'pending')
                    <p class="mt-3 text-center text-sm text-slate-600">{{ __('messages.checkout.pay_later') }}: {{ $paymentMethods['pay_later']['instructions'] ?: __('messages.checkout.pay_later') }}</p>
                @endif

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

                @php $latestAttempt = $reservation->paymentAttempts->last(); @endphp
                @if ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed' && $latestAttempt && ($isDevEnvironment || $latestAttempt->provider !== 'pay_later'))
                    <form action="{{ route('checkout.complete', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="mt-6">
                        @csrf
                        <button type="submit" class="w-full rounded-md border border-slate-300 bg-white px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">
                            {{ __('messages.checkout.simulate') }}
                        </button>
                    </form>
                @elseif ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed' && $latestAttempt?->provider === 'pay_later' && ! $isDevEnvironment)
                    <p class="mt-6 text-center text-sm text-slate-500">{{ __('messages.checkout.simulate_unavailable') }}</p>
                @endif

                @if (in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true))
                    <form action="{{ route('checkout.cancel', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="mt-3" onsubmit="return confirm('{{ __('messages.checkout.cancel_confirmation') }}');">
                        @csrf
                        <button type="submit" class="w-full rounded-md border border-red-200 bg-white px-4 py-2.5 font-medium text-red-700 hover:bg-red-50">
                            {{ __('messages.checkout.cancel_reservation') }}
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
</body>
</html>
