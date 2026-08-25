{{-- resources/views/components/badge.blade.php --}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'outline' => false,
    'dot' => false,
    'closeable' => false,
])

@php
    $classes = [
        'badge',
        "badge-{$size}",
        $outline ? 'badge-outline ' . $variant : "badge-{$variant}",
        $dot ? 'badge-dot' : '',
        $closeable ? 'badge-closeable' : '',
    ];
@endphp

<span {{ $attributes->merge(['class' => implode(' ', array_filter($classes))]) }}>
    {{ $slot }}
    @if ($closeable)
        <button type="button" aria-label="Remove badge" onclick="this.parentElement.remove()">
            ✕
        </button>
    @endif
</span>
