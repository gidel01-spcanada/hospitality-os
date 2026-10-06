<div class="checkout-account-activation" data-account-activation-notice role="status" aria-labelledby="checkout-activation-title">
    <div>
        <h2 id="checkout-activation-title">{{ __('messages.checkout.activation_title') }}</h2>
        @if (session('account_activation_email_failed'))
            <p>{{ __('messages.checkout.activation_email_failed') }}</p>
        @else
            <p>{{ __('messages.checkout.activation_description', ['email' => $reservation->email]) }}</p>
        @endif
        <p><strong>{{ in_array($reservation->status, ['confirmed', 'completed'], true) ? __('messages.checkout.activation_after_payment') : __('messages.checkout.activation_continue') }}</strong></p>
    </div>
    <form method="POST" action="{{ route('checkout.password-setup', ['reservation' => $reservation, 'token' => $token]) }}">
        @csrf
        <button type="submit" class="btn btn-ghost btn-small">{{ __('messages.checkout.activation_resend') }}</button>
    </form>
</div>