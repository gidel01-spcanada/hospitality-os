@extends('emails.layouts.reservation')

@section('email-preheader', __('messages.receipts.proof_rejected_heading'))
@section('email-eyebrow', __('messages.transactional.eyebrow_payment'))
@section('email-title', __('messages.receipts.proof_rejected_heading'))

@section('email-content')
    <p>{{ __('messages.receipts.proof_rejected_intro', ['reference' => $reservation_ref]) }}</p>
    <p><strong>{{ __('messages.receipts.proof_rejection_reason') }}:</strong> {{ $reason }}</p>
    <p>{{ __('messages.receipts.proof_rejected_deadline') }}</p>
    <x-email.button :href="$checkout_url">{{ __('messages.receipts.proof_rejected_action') }}</x-email.button>
@endsection
