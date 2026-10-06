@props([
    'title',
    'label' => null,
    // CSS selector of a form whose current (not yet submitted) values should be added to the shared link.
    'state' => null,
    // Optional allow-list of form fields to share; "from:to" renames a field (e.g. "adults:guests").
    'params' => null,
    'anchor' => null,
])
@php
    $sensitiveKeys = ['token', 'signature', 'expires', '_token', '_method', 'checkout_return', 'page'];
    $shareQuery = collect(request()->query())
        ->except($sensitiveKeys)
        ->reject(fn ($value) => $value === null || $value === '' || $value === [])
        ->all();
    $shareUrl = url()->current() . ($shareQuery ? '?' . http_build_query($shareQuery) : '') . ($anchor ? '#' . ltrim($anchor, '#') : '');
    $encodedShareUrl = urlencode($shareUrl);
    $encodedShareTitle = urlencode($title);
    $copyAttributes = 'data-copy-share-link-success="' . e(__('messages.properties.share_link_copied')) . '" data-copy-share-link-error="' . e(__('messages.properties.share_link_copy_failed')) . '"';
@endphp
<details {{ $attributes->merge(['class' => 'share-panel']) }} data-share-panel data-share-url="{{ $shareUrl }}" data-share-title="{{ $title }}" @if ($state) data-share-state="{{ $state }}" @endif @if ($params) data-share-params="{{ $params }}" @endif>
    <summary>{{ $label ?? __('messages.properties.share') }}</summary>
    <div class="share-links">
        <button type="button" data-native-share hidden>{{ __('messages.properties.share_native') }}</button>
        <a data-share-network="whatsapp" href="https://wa.me/?text={{ $encodedShareTitle }}%20{{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_whatsapp') }}</a>
        <a data-share-network="facebook" href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_facebook') }}</a>
        <a data-share-network="x" href="https://twitter.com/intent/tweet?text={{ $encodedShareTitle }}&url={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_x') }}</a>
        <a data-share-network="linkedin" href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('messages.properties.share_linkedin') }}</a>
        <a data-share-network="email" href="mailto:?subject={{ rawurlencode($title) }}&body={{ rawurlencode($shareUrl) }}">{{ __('messages.properties.share_email') }}</a>
        <button type="button" data-copy-share-link="{{ $shareUrl }}" {!! $copyAttributes !!}>{{ __('messages.properties.share_copy') }}</button>
        <button type="button" data-copy-share-link="{{ $shareUrl }}" {!! $copyAttributes !!}>{{ __('messages.properties.share_instagram') }}</button>
        <button type="button" data-copy-share-link="{{ $shareUrl }}" {!! $copyAttributes !!}>{{ __('messages.properties.share_tiktok') }}</button>
    </div>
    <p class="form-help" data-copy-share-link-status aria-live="polite" hidden></p>
</details>
