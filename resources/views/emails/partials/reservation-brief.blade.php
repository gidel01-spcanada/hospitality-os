@php
    $emailBrief = $reservation_context;
    $emailBriefDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('D d M Y') : '—';
@endphp
<x-email.card :title="__('messages.checkout.order_summary')" tone="muted">
    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        <x-email.row :label="__('messages.checkout.establishment')">{{ $emailBrief['establishment']['name'] ?? ($establishment_name ?? '') }}</x-email.row>
        <x-email.row :label="__('messages.checkout.property')">{{ $emailBrief['property']['name'] ?? '' }}</x-email.row>
        <x-email.row :label="__('messages.checkout.reference')" :emphasis="true">{{ $emailBrief['reference'] }}</x-email.row>
        <x-email.row :label="__('messages.properties.check_in')">{{ $emailBriefDate($emailBrief['check_in'] ?? null) }}</x-email.row>
        <x-email.row :label="__('messages.properties.check_out')">{{ $emailBriefDate($emailBrief['check_out'] ?? null) }}</x-email.row>
    </table>
</x-email.card>
