{{-- resources/views/components/form-group.blade.php --}}
@props([
    'title' => null,
    'description' => null,
    'cols' => 1,
])

<div class="form-group-wrapper">
    @if ($title)
        <div class="form-section-header">
            <h3>{{ $title }}</h3>
            @if ($description)
                <p class="text-secondary">{{ $description }}</p>
            @endif
        </div>
    @endif

    @if ($cols > 1)
        <div class="form-row cols-{{ $cols }}">
            {{ $slot }}
        </div>
    @else
        <div class="form-fields">
            {{ $slot }}
        </div>
    @endif
</div>
