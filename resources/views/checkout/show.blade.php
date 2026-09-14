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
                        @if ($reservation->property?->establishment?->secondary_currency && $reservation->property?->establishment?->secondaryDisplayAmount((float) $reservation->total_amount) !== null)
                            <dd class="mt-1 text-sm text-slate-500">≈ {{ number_format($reservation->property->establishment->secondaryDisplayAmount((float) $reservation->total_amount), 2, ',', ' ') }} {{ $reservation->property->establishment->secondary_currency }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('messages.checkout.client') }}</dt>
                        <dd class="mt-1">{{ $reservation->guest?->full_name ?? $reservation->email }}</dd>
                    </div>
                </dl>

                @if ($reservation->priceLines->isNotEmpty())
                    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-md font-semibold text-slate-900">{{ __('messages.reservation.amounts') }}</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            @foreach ($reservation->priceLines as $line)
                                <li class="flex items-center justify-between border-b border-slate-200/60 pb-1.5 last:border-b-0 last:pb-0">
                                    <span>{{ $line->label }}</span>
                                    <span class="font-medium">{{ number_format((float) $line->amount, 0, ',', ' ') }} {{ $line->currency }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-lg font-semibold">{{ __('messages.checkout.methods') }}</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                        @foreach ($paymentMethods as $provider => $method)
                            <li>• {{ __('messages.checkout.' . $provider) }}@if ($method['instructions']) — {{ $method['instructions'] }}@endif
                                @if (in_array($provider, ['interac', 'wise', 'revolut'], true))
                                    @php
                                        $internationalAmount = $reservation->property?->establishment?->secondaryDisplayAmount((float) $reservation->total_amount);
                                        $interacAmount = app(\App\Services\InternationalCurrencyConverter::class)->toCad((float) $internationalAmount, $reservation->property?->establishment?->secondary_currency);
                                    @endphp
                                    @if ($interacAmount !== null)
                                        <div class="ml-4 mt-1 flex items-center gap-2 text-xs"><span>{{ __('messages.checkout.' . $provider . '_amount', ['amount' => number_format($interacAmount, 2, ',', ' '), 'currency' => $provider === 'interac' ? 'CAD' : $reservation->property?->establishment?->secondary_currency]) }}</span><button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px]" data-copy-text="{{ number_format($interacAmount, 2, '.', '') }} {{ $provider === 'interac' ? 'CAD' : $reservation->property?->establishment?->secondary_currency }}">{{ __('messages.checkout.copy') }}</button></div>
                                    @endif
                                    @if (!empty($method['email']))<div class="ml-4 flex items-center gap-2 text-xs"><span>{{ __('messages.checkout.' . $provider . '_email', ['email' => $method['email']]) }}</span><button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px]" data-copy-text="{{ $method['email'] }}">{{ __('messages.checkout.copy') }}</button></div>@endif
                                    @if ($provider === 'interac' && !empty($method['security_question']))<div class="ml-4 text-xs">{{ __('messages.checkout.interac_question', ['question' => $method['security_question']]) }}</div>@endif
                                    @if ($provider === 'interac' && !empty($method['security_answer']))<div class="ml-4 flex items-center gap-2 text-xs"><span>{{ __('messages.checkout.interac_answer', ['answer' => $method['security_answer']]) }}</span><button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px]" data-copy-text="{{ $method['security_answer'] }}">{{ __('messages.checkout.copy') }}</button></div>@endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @if ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed')
                    @php
                        $nonPaypalMethods = collect($paymentMethods)->except('paypal')->all();
                        $cancellationFeePercent = (float) ($reservation->property?->establishment?->cancellation_fee_percent ?? 0);
                        $cancellationFeeHoldAmount = $reservation->property?->establishment?->cancellationFeeHoldAmount($reservation) ?? 0.0;
                        $onlineMethods = collect($paymentMethods)->except(['pay_later', 'interac'])->all();
                    @endphp

                    @if (isset($paymentMethods['pay_later']) && $cancellationFeePercent > 0 && $cancellationFeeHoldAmount > 0 && count($onlineMethods) > 0)
                        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                            <p class="font-semibold">{{ __('messages.checkout.pay_later_guarantee_title') }}</p>
                            <p class="mt-1">{{ __('messages.checkout.pay_later_guarantee_notice', [
                                'amount' => number_format($cancellationFeeHoldAmount, 0, ',', ' '),
                                'currency' => $reservation->currency,
                                'percent' => rtrim(rtrim(number_format($cancellationFeePercent, 2, ',', ' '), '0'), ','),
                            ]) }}</p>
                        </div>
                    @endif

                    @if (count($nonPaypalMethods) > 0)
                        <form action="{{ route('checkout.start', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="space-y-4">
                            @csrf
                            @if (count($nonPaypalMethods) === 1)
                                @php $singleProvider = array_key_first($nonPaypalMethods); @endphp
                                <input type="hidden" name="provider" value="{{ $singleProvider }}">
                                <div>
                                    <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('messages.checkout.mode') }}</span>
                                    <div class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800">
                                        {{ __('messages.checkout.' . $singleProvider) }}
                                    </div>
                                </div>
                            @else
                                <div>
                                    <label for="provider" class="mb-1 block text-sm font-medium text-slate-700">{{ __('messages.checkout.mode') }}</label>
                                    <select id="provider" name="provider" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none">
                                        @foreach ($nonPaypalMethods as $provider => $method)
                                            <option value="{{ $provider }}">{{ __('messages.checkout.' . $provider) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if (isset($paymentMethods['pay_later']) && $cancellationFeePercent > 0 && $cancellationFeeHoldAmount > 0 && count($onlineMethods) > 1)
                                <div>
                                    <label for="guarantee_provider" class="mb-1 block text-xs font-medium text-slate-700">{{ __('messages.checkout.guarantee_provider_label') }}</label>
                                    <select id="guarantee_provider" name="guarantee_provider" class="w-full rounded-md border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:outline-none">
                                        @foreach ($onlineMethods as $onlineProvider => $method)
                                            <option value="{{ $onlineProvider }}">{{ __('messages.checkout.' . $onlineProvider) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <button type="submit" class="w-full rounded-md bg-amber-500 px-4 py-2.5 font-medium text-white hover:bg-amber-600">
                                {{ __('messages.checkout.start') }}
                            </button>
                        </form>
                    @endif

                    @if (isset($paymentMethods['paypal']))
                        <div class="@if(count($nonPaypalMethods) > 0) mt-6 border-t border-slate-200 pt-4 @endif">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('messages.checkout.paypal_smart_buttons_heading') }}</p>
                            <div id="paypal-button-container"></div>
                        </div>
                    @endif
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
                @elseif ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed' && in_array($latestAttempt?->provider, ['pay_later', 'interac'], true) && ! $isDevEnvironment)
                    <p class="mt-6 text-center text-sm text-slate-500">{{ __('messages.checkout.simulate_unavailable') }}</p>
                @endif

                @if (in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true))
                    @php $cancellationFee = $reservation->property?->establishment?->cancellationFeeFor($reservation) ?? 0.0; @endphp
                    @if ($cancellationFee > 0)
                        <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            {{ __('messages.checkout.cancellation_fee_warning', ['amount' => number_format($cancellationFee, 0, ',', ' '), 'currency' => $reservation->currency]) }}
                        </p>
                    @endif
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

    @if (isset($paymentMethods['paypal']) && $reservation->status !== 'cancelled' && $reservation->status !== 'confirmed')
        @php
            $paypalClientId = config('services.paypal.client_id') ?: 'test';
            $supportedPaypalCurrencies = ['EUR', 'USD', 'GBP', 'CAD', 'AUD', 'CHF'];
            $paypalCurrency = in_array($reservation->currency, $supportedPaypalCurrencies, true)
                ? $reservation->currency
                : ($reservation->property?->establishment?->secondary_currency ?? 'EUR');
            if (! in_array($paypalCurrency, $supportedPaypalCurrencies, true)) {
                $paypalCurrency = 'EUR';
            }
        @endphp
        <script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency={{ $paypalCurrency }}&components=buttons,funding-eligibility"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const container = document.getElementById('paypal-button-container');
                if (container && typeof paypal !== 'undefined') {
                    paypal.Buttons({
                        style: {
                            layout: 'vertical',
                            color: 'gold',
                            shape: 'rect',
                            label: 'paypal'
                        },
                        createOrder: function () {
                            return fetch('{{ route("checkout.paypal.create", ["reservation" => $reservation, "token" => $token]) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            })
                            .then(function (res) {
                                if (!res.ok) {
                                    throw new Error('Failed to create PayPal order');
                                }
                                return res.json();
                            })
                            .then(function (data) {
                                return data.orderID;
                            });
                        },
                        onApprove: function (data) {
                            return fetch('{{ route("checkout.paypal.capture", ["reservation" => $reservation, "token" => $token]) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ orderID: data.orderID })
                            })
                            .then(function (res) {
                                return res.json();
                            })
                            .then(function (details) {
                                if (details.redirect_url) {
                                    window.location.href = details.redirect_url;
                                } else {
                                    window.location.reload();
                                }
                            });
                        },
                        onError: function (err) {
                            console.error('PayPal SDK error:', err);
                        }
                    }).render('#paypal-button-container');
                }
            });
        </script>
    @endif
</body>
</html>
