@extends('emails.layouts.reservation')

@section('email-eyebrow', __('messages.transactional.eyebrow_account', ['brand' => \App\Support\PlatformBrand::name()]))
@section('email-title', __('messages.account_setup.set_password'))

@section('email-content')
    <p style="margin:0 0 10px;font-size:17px;font-weight:bold;color:#0a3f35;">{{ __('messages.transactional.welcome', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
    <p style="margin:0 0 10px;">{{ __('messages.transactional.account_context', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
    <p style="margin:0 0 16px;">{{ $account_message }}</p>
    @if (filled($reservation_reference ?? null))
        <x-email.card tone="muted">
            <p style="margin:0 0 2px;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b70;">{{ $reference_label ?? __('messages.account_setup.reservation_number') }}</p>
            <p data-account-reservation-reference style="margin:0;font-size:20px;font-weight:bold;color:#0a3f35;">{{ $reservation_reference }}</p>
        </x-email.card>
    @endif
    <x-email.button :href="$action_url">{{ __('messages.account_setup.set_password') }}</x-email.button>
    <p style="margin:16px 0 4px;font-size:13px;color:#5b6b70;">{{ __('messages.account_setup.expiry') }}</p>
    <p style="margin:0;font-size:12px;color:#5b6b70;">{{ __('messages.transactional.security_ignore') }}</p>
@endsection
