@props(['amount', 'property'])
{{ \App\Support\ReservationSummary::money($amount, $property->currency) }}