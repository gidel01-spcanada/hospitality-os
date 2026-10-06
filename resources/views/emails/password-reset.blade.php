@extends('emails.layouts.reservation')

@section('email-eyebrow', __('messages.transactional.eyebrow_security'))
@section('email-title', __('messages.transactional.reset_title'))

@section('email-content')
    <p style="margin:0 0 16px;">{{ __('messages.transactional.reset_context', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
    <x-email.button :href="$action_url">{{ __('messages.transactional.reset_action') }}</x-email.button>
    <p style="margin:16px 0 4px;font-size:13px;color:#5b6b70;">{{ __('messages.transactional.reset_expiry', ['hours' => max(1, (int) round($expiry_minutes / 60))]) }}</p>
    <p style="margin:0;font-size:12px;color:#5b6b70;">{{ __('messages.transactional.security_ignore') }}</p>
@endsection
