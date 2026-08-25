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
                class="input-{{ $size }} {{ $error ? 'is-error' : '' }}"
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
            class="input-{{ $size }} {{ $error ? 'is-error' : '' }}"
            {{ $attributes }}
        />
    @endif

    @if ($error)
        <span class="form-error">{{ $error }}</span>
    @elseif ($help)
        <span class="form-help">{{ $help }}</span>
    @endif
</div>
