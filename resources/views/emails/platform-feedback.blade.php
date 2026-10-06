@extends('emails.layouts.reservation')

@section('email-eyebrow', __('messages.transactional.eyebrow_feedback'))
@section('email-title', __('messages.transactional.feedback_title', ['brand' => \App\Support\PlatformBrand::name()]))

@section('email-content')
    <p style="margin:0 0 16px;">{{ __('messages.transactional.feedback_context') }}</p>
    <x-email.card tone="muted">
        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <x-email.row :label="__('messages.checkout.client')">{{ $feedback->name }}</x-email.row>
            <x-email.row label="Email">{{ $feedback->email }}</x-email.row>
            <x-email.row :label="__('messages.transactional.feedback_category')">{{ $feedback->category }}</x-email.row>
        </table>
    </x-email.card>
    <x-email.card>
        <div style="padding:2px 0 2px 14px;border-left:3px solid #c9a24a;white-space:pre-wrap;">{{ $feedback->message }}</div>
    </x-email.card>
@endsection
