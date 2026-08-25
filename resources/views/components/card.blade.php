{{-- resources/views/components/card.blade.php --}}
@props([
    'variant' => 'default',
    'padding' => 6,
    'clickable' => false,
    'href' => null,
])

@php
    $classes = [
        'card',
        match($variant) {
            'elevated' => 'elevated',
            'outlined' => 'outlined',
            'flat' => 'flat',
            default => '',
        },
        $clickable ? 'clickable' : '',
        "p-{$padding}",
    ];

    $tag = $clickable && $href ? 'a' : 'div';
@endphp

@if ($tag === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => implode(' ', array_filter($classes))]) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => implode(' ', array_filter($classes))]) }}>
        {{ $slot }}
    </div>
@endif
