{{-- resources/views/components/input.blade.php --}}
@props([
    'type' => 'text',
    'name' => '',
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'size' => 'md',
    'error' => null,
    'help' => null,
    'prefix' => null,
    'suffix' => null,
])

@php
    $resolvedError = $error ?: $errors->first($name);
    $descriptionId = $resolvedError ? $name . '-error' : ($help ? $name . '-help' : null);
@endphp

<div class="form-group">
    @if ($label)
        <label for="{{ $name }}">
            {{ $label }}
            @if ($required)
                <span class="required">*</span>
            @else
                <span class="optional">{{ __('messages.forms.optional') ?? 'Optional' }}</span>
            @endif
        </label>
    @endif

    @if ($prefix || $suffix)
        <div class="input-wrapper {{ $suffix ? 'has-suffix' : '' }}">
            @if ($prefix)
                <span class="input-prefix">{{ $prefix }}</span>
            @endif
            
            <input
                type="{{ $type }}"
                name="{{ $name }}"
                id="{{ $name }}"
                @if ($value) value="{{ $value }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($required) required @endif
                @if ($disabled) disabled @endif
                @if ($resolvedError) aria-invalid="true" @endif
                @if ($descriptionId) aria-describedby="{{ $descriptionId }}" @endif
                class="input-{{ $size }} {{ $resolvedError ? 'is-error' : '' }}"
                {{ $attributes }}
            />
            
            @if ($suffix)
                <span class="input-suffix">{{ $suffix }}</span>
            @endif
        </div>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            @if ($value) value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($resolvedError) aria-invalid="true" @endif
            @if ($descriptionId) aria-describedby="{{ $descriptionId }}" @endif
            class="input-{{ $size }} {{ $resolvedError ? 'is-error' : '' }}"
            {{ $attributes }}
        />
    @endif

    @if ($resolvedError)
        <span id="{{ $name }}-error" class="form-error">{{ $resolvedError }}</span>
    @elseif ($help)
        <span id="{{ $name }}-help" class="form-help">{{ $help }}</span>
    @endif
</div>
