{{-- resources/views/components/icon.blade.php --}}
@props([
    'name',
    'size' => 'md',
    'color' => null,
])

@php
    $sizeClass = match($size) {
        'xs' => 'icon-xs',
        'sm' => 'icon-sm',
        'md' => 'icon-md',
        'lg' => 'icon-lg',
        'xl' => 'icon-xl',
        '2xl' => 'icon-2xl',
        default => 'icon-md',
    };

    $colorClass = $color ? "icon-{$color}" : '';

    $classes = [
        'icon',
        $sizeClass,
        $colorClass,
    ];
@endphp

@php
    // Build the icon file path
    $iconPath = resource_path("icons/{$name}.svg");
@endphp

@if (file_exists($iconPath))
    <svg {{ $attributes->merge(['class' => implode(' ', array_filter($classes)), 'viewBox' => '0 0 24 24', 'fill' => 'currentColor', 'aria-hidden' => 'true']) }}>
        @include("icons.{$name}")
    </svg>
@else
    {{-- Fallback: display icon name or empty if file not found --}}
    <span title="Icon not found: {{ $name }}" aria-hidden="true">●</span>
@endif
