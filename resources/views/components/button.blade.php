{{-- resources/views/components/button.blade.php --}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'full' => false,
    'disabled' => false,
    'loading' => false,
    'tag' => 'button',
    'href' => null,
    'type' => 'button',
])

@php
    $classes = [
        'btn',
        "btn-{$size}",
        "btn-{$variant}",
        $full ? 'btn-full' : '',
        $loading ? 'is-loading' : '',
    ];

    $attrs = array_merge($attributes->getAttributes(), [
        'class' => implode(' ', array_filter($classes)),
    ]);

    if ($tag === 'a') {
        $attrs['href'] = $href;
    } elseif ($tag === 'button') {
        $attrs['type'] = $type;
        if ($disabled || $loading) {
            $attrs['disabled'] = true;
        }
    }
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge($attrs) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge($attrs) }}>
        {{ $slot }}
    </button>
@endif
