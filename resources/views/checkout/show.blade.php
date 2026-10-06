@extends('layouts.app')

@section('title', __('messages.checkout.badge') . ' | ' . \App\Support\PlatformBrand::name())

@section('content')
<section class="checkout-page">
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
                @if (session('checkout_login_prompt'))
                    @guest
                        <span data-checkout-login-prompt>{{ __('messages.checkout.password_setup_login_prompt') }}</span>
                        <a class="font-semibold underline" href="{{ route('login') }}">{{ __('messages.checkout.password_setup_login_action') }}</a>
                    @endguest
                @endif
            </div>
        @endif

        @if ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed')
            <div class="checkout-mobile-summary">
                <div>
                    <span>{{ __('messages.reservation.total') }}</span>
                    <strong>{{ number_format((float) $reservation->total_amount, (float) $reservation->total_amount == round((float) $reservation->total_amount) ? 0 : 2, app()->getLocale() === 'en' ? '.' : ',', app()->getLocale() === 'en' ? ',' : ' ') }} {{ $reservation->currency }}</strong>
                </div>
                <a class="btn btn-primary" href="#checkout-payment">{{ __('messages.checkout.continue_to_payment') }}</a>
            </div>
        @endif

        @if ($accountActivationRequired ?? false)
            @include('checkout.partials.account-activation')
        @endif

        <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
            @include('checkout.partials.order-summary')

            <aside id="checkout-payment" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @php
                    $latestAttempt = $reservation->paymentAttempts->last();
                    $showPaymentOptions = ! $linkExpired && in_array($reservation->status, ['pending', 'pending_payment', 'payment_failed'], true);
                    $awaitingValidation = $reservation->status === 'pending_validation';
                @endphp

                @if ($linkExpired)
                    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ __('messages.checkout.link_expired') }}
                    </div>
                @endif

                @if ($awaitingValidation)
                    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {{ __('messages.checkout.awaiting_validation') }}
                    </div>
                @endif

                @if ($showPaymentOptions)
                    @php
                        $cancellationFeePercent = (float) ($reservation->property?->establishment?->cancellation_fee_percent ?? 0);
                        $cancellationFeeHoldAmount = $reservation->property?->establishment?->cancellationFeeHoldAmount($reservation) ?? 0.0;
                        $onlineMethods = collect($paymentMethods)->except(['pay_later', 'interac', 'wise', 'revolut']);
                        $providerOrder = ['paypal', 'fedapay', 'cinetpay', 'mpesa', 'pay_later', 'interac', 'wise', 'revolut'];
                        $orderedMethods = collect($providerOrder)->filter(fn ($provider) => isset($paymentMethods[$provider]))->mapWithKeys(fn ($provider) => [$provider => $paymentMethods[$provider]]);
                        $selectedProvider = $reservation->paymentAttempts->last()?->provider;
                        $useAccordion = $orderedMethods->count() > 1;
                        $defaultOpenProvider = $orderedMethods->has($selectedProvider) ? $selectedProvider : $orderedMethods->keys()->first();
                    @endphp

                    <div class="space-y-3">
                        @foreach ($orderedMethods as $provider => $method)
                            @php
                                $providerAttempt = $reservation->paymentAttempts->where('provider', $provider)->last();
                                $shouldOpenAccordion = $useAccordion && $provider === $defaultOpenProvider;
                            @endphp
                            @if ($useAccordion)
                                <details class="group rounded-lg border border-slate-200" name="payment-method-accordion"@if($shouldOpenAccordion) open @endif>
                                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-medium text-slate-800">
                                        <span>{{ __('messages.checkout.' . $provider) }}</span>
                                        <span class="text-slate-400 transition-transform group-open:rotate-180">&#9662;</span>
                                    </summary>
                                    <div class="border-t border-slate-200 px-4 py-4">
                                        @include('checkout.partials.payment-method', ['provider' => $provider, 'method' => $method, 'providerAttempt' => $providerAttempt, 'onlineMethods' => $onlineMethods, 'cancellationFeePercent' => $cancellationFeePercent, 'cancellationFeeHoldAmount' => $cancellationFeeHoldAmount])
                                    </div>
                                </details>
                            @else
                                <div class="rounded-lg border border-slate-200 px-4 py-4">
                                    <p class="mb-3 font-medium text-slate-800">{{ __('messages.checkout.' . $provider) }}</p>
                                    @include('checkout.partials.payment-method', ['provider' => $provider, 'method' => $method, 'providerAttempt' => $providerAttempt, 'onlineMethods' => $onlineMethods, 'cancellationFeePercent' => $cancellationFeePercent, 'cancellationFeeHoldAmount' => $cancellationFeeHoldAmount])
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if($reservation->paymentAttempts->isNotEmpty())
                    <div class="mt-6 border-t border-slate-200 pt-4">
                        <h3 class="text-md font-semibold">{{ __('messages.checkout.attempts') }}</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach($reservation->paymentAttempts as $attempt)
                                <li class="rounded-md bg-slate-50 px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">{{ __('messages.checkout.' . $attempt->provider) }}</span>
                                        <span class="text-slate-500">{{ $attempt->status }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $attempt->provider_reference }}</div>
                                    @if (data_get($attempt->payload, 'payment_proof.path'))
                                        <a class="mt-2 inline-block text-sm text-amber-700 underline" href="{{ route('reservations.payment-proof.download', ['reservation' => $reservation, 'attempt' => $attempt, 'token' => $token]) }}">{{ data_get($attempt->payload, 'payment_proof.original_name') ?: __('messages.receipts.download_proof') }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php $canSimulatePayment = $isDevEnvironment || auth()->user()?->canManageReservations(); @endphp
                @if ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed' && $latestAttempt && $canSimulatePayment)
                    <form action="{{ route('checkout.complete', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="mt-6">
                        @csrf
                        <button type="submit" class="w-full rounded-md border border-slate-300 bg-white px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">
                            {{ __('messages.checkout.simulate') }}
                        </button>
                    </form>
                @elseif ($reservation->status !== 'cancelled' && $reservation->status !== 'confirmed' && $latestAttempt && ! $canSimulatePayment)
                    <p class="mt-6 text-center text-sm text-slate-500">{{ __('messages.checkout.simulate_unavailable') }}</p>
                @endif

                @if ($showPaymentOptions)
                    @php $cancellationFee = $reservation->property?->establishment?->cancellationFeeFor($reservation) ?? 0.0; @endphp
                    @if ($cancellationFee > 0)
                        <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            {{ __('messages.checkout.cancellation_fee_warning', ['amount' => number_format($cancellationFee, 0, ',', ' '), 'currency' => $reservation->currency]) }}
                        </p>
                    @endif
                    <form action="{{ route('checkout.cancel', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="mt-3" data-confirm-message="{{ __('messages.checkout.cancel_confirmation') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-md border border-red-200 bg-white px-4 py-2.5 font-medium text-red-700 hover:bg-red-50">
                            {{ __('messages.checkout.cancel_reservation') }}
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
</section>

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
@endsection
