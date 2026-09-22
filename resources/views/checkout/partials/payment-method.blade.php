@php
    $hasProof = (bool) data_get($providerAttempt?->payload, 'payment_proof.path');
@endphp

@if ($provider === 'paypal')
    @if (!empty($method['instructions']))
        <p class="mb-3 text-sm text-slate-600">{{ $method['instructions'] }}</p>
    @endif
    <div id="paypal-button-container"></div>

@elseif (in_array($provider, ['fedapay', 'cinetpay', 'mpesa'], true))
    @if (!empty($method['instructions']))
        <p class="mb-3 text-sm text-slate-600">{{ $method['instructions'] }}</p>
    @endif
    <form action="{{ route('checkout.start', ['reservation' => $reservation, 'token' => $token]) }}" method="POST">
        @csrf
        <input type="hidden" name="provider" value="{{ $provider }}">
        <button type="submit" class="w-full rounded-md bg-amber-500 px-4 py-2.5 font-medium text-white hover:bg-amber-600">
            {{ __('messages.checkout.pay_online_action') }}
        </button>
    </form>

@elseif ($provider === 'pay_later')
    @if (!empty($method['instructions']))
        <p class="mb-3 text-sm text-slate-600">{{ $method['instructions'] }}</p>
    @endif

    @if ($cancellationFeePercent > 0 && $cancellationFeeHoldAmount > 0 && $onlineMethods->isNotEmpty())
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">{{ __('messages.checkout.pay_later_guarantee_title') }}</p>
            <p class="mt-1">{{ __('messages.checkout.pay_later_guarantee_notice', [
                'amount' => number_format($cancellationFeeHoldAmount, 0, ',', ' '),
                'currency' => $reservation->currency,
                'percent' => rtrim(rtrim(number_format($cancellationFeePercent, 2, ',', ' '), '0'), ','),
            ]) }}</p>
        </div>
    @endif

    @if ($providerAttempt)
        <p class="text-sm text-slate-600">{{ __('messages.checkout.pay_on_arrival_confirmed') }}</p>
    @else
        <form action="{{ route('checkout.start', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" class="space-y-3">
            @csrf
            <input type="hidden" name="provider" value="pay_later">
            @if ($cancellationFeePercent > 0 && $cancellationFeeHoldAmount > 0 && $onlineMethods->count() > 1)
                <div>
                    <label for="guarantee_provider" class="mb-1 block text-xs font-medium text-slate-700">{{ __('messages.checkout.guarantee_provider_label') }}</label>
                    <select id="guarantee_provider" name="guarantee_provider" class="w-full rounded-md border border-slate-300 px-3 py-2 text-xs focus:border-amber-500 focus:outline-none">
                        @foreach ($onlineMethods as $onlineProvider => $onlineMethod)
                            <option value="{{ $onlineProvider }}">{{ __('messages.checkout.' . $onlineProvider) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="w-full rounded-md bg-amber-500 px-4 py-2.5 font-medium text-white hover:bg-amber-600">
                {{ __('messages.checkout.pay_on_arrival_action') }}
            </button>
        </form>
    @endif

@elseif (in_array($provider, ['interac', 'wise', 'revolut'], true))
    @php
        $internationalAmount = $reservation->property?->establishment?->secondaryDisplayAmount((float) $reservation->total_amount);
        $convertedAmount = app(\App\Services\InternationalCurrencyConverter::class)->toCad((float) $internationalAmount, $reservation->property?->establishment?->secondary_currency);
        $providerCurrency = $provider === 'interac' ? 'CAD' : $reservation->property?->establishment?->secondary_currency;
    @endphp

    @if (!empty($method['instructions']))
        <p class="mb-3 text-sm text-slate-600">{{ $method['instructions'] }}</p>
    @endif

    <div class="space-y-2 text-sm">
        @if ($convertedAmount !== null)
            <div class="flex items-center gap-2"><span>{{ __('messages.checkout.' . $provider . '_amount', ['amount' => number_format($convertedAmount, 2, ',', ' '), 'currency' => $providerCurrency]) }}</span><button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px]" data-copy-text="{{ number_format($convertedAmount, 2, '.', '') }} {{ $providerCurrency }}">{{ __('messages.checkout.copy') }}</button></div>
        @endif
        @if (!empty($method['email']))
            <div class="flex items-center gap-2"><span>{{ __('messages.checkout.' . $provider . '_email', ['email' => $method['email']]) }}</span><button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px]" data-copy-text="{{ $method['email'] }}">{{ __('messages.checkout.copy') }}</button></div>
        @endif
        @if ($provider === 'interac' && !empty($method['security_question']))
            <div>{{ __('messages.checkout.interac_question', ['question' => $method['security_question']]) }}</div>
        @endif
        @if ($provider === 'interac' && !empty($method['security_answer']))
            <div class="flex items-center gap-2"><span>{{ __('messages.checkout.interac_answer', ['answer' => $method['security_answer']]) }}</span><button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px]" data-copy-text="{{ $method['security_answer'] }}">{{ __('messages.checkout.copy') }}</button></div>
        @endif
    </div>

    @if ($hasProof)
        <p class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ __('messages.checkout.proof_already_submitted') }}</p>
    @else
        <details class="mt-4">
            <summary class="inline-flex w-full cursor-pointer list-none items-center justify-center rounded-md bg-amber-500 px-4 py-2.5 text-center font-medium text-white hover:bg-amber-600">
                {{ __('messages.checkout.i_have_paid') }}
            </summary>
            <form action="{{ route('checkout.offline-proof', ['reservation' => $reservation, 'token' => $token]) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-2">
                @csrf
                <input type="hidden" name="provider" value="{{ $provider }}">
                <label class="block text-xs font-medium text-slate-700" for="payment_proof_{{ $provider }}">{{ __('messages.checkout.upload_proof_label') }}</label>
                <input id="payment_proof_{{ $provider }}" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required class="block w-full text-sm">
                <button type="submit" class="w-full rounded-md border border-slate-300 bg-white px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">
                    {{ __('messages.checkout.upload_proof_submit') }}
                </button>
            </form>
        </details>
    @endif
@endif
