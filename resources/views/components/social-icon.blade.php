@props(['network'])
@php($label = __('messages.admin.social_' . $network))
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>
    @switch($network)
        @case('facebook')
            <path d="M14 21v-8h3l.5-3H14V8c0-.9.3-1.5 1.6-1.5H18V3.8c-.8-.1-1.7-.2-2.6-.2-2.7 0-4.4 1.6-4.4 4.5V10H8v3h3v8" fill="currentColor" stroke="none"/>
            @break
        @case('instagram')
            <rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.7" cy="6.6" r=".8" fill="currentColor" stroke="none"/>
            @break
        @case('tiktok')
            <path d="M14 4v10.7a4.1 4.1 0 1 1-3.4-4.05M14 4c.5 2.9 2.3 4.6 5 4.9"/>
            @break
        @case('youtube')
            <rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none"/>
            @break
        @case('x')
            <path d="M4 3.5 20 20.5M19.5 3.5 4.5 20.5M4.5 3.5h4l11 17h-4z"/>
            @break
        @case('linkedin')
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="7.2" cy="8" r=".8" fill="currentColor" stroke="none"/><path d="M7.2 11v6m4-6v6m0-3.4a2.6 2.6 0 0 1 5.2 0V17"/>
            @break
        @case('whatsapp')
            <path d="M20.3 11.7a8.2 8.2 0 0 1-12.1 7.2L3 20l1.2-4.9a8.2 8.2 0 1 1 16.1-3.4Z"/><path d="M8.2 8.4c.3-.6.6-.6.9-.6h.5c.2 0 .4.1.5.4l.8 1.9c.1.2.1.4-.1.6l-.6.8c-.2.2-.2.4 0 .6.4.7 1.2 1.5 2.1 1.9.2.1.4.1.6-.1l.8-1c.2-.2.4-.2.6-.1l1.8.9c.2.1.3.2.3.4 0 .4-.2 1.2-.7 1.5-.5.4-1.1.6-1.8.4-1.2-.3-2.5-.9-3.8-2.1-1.1-1-1.9-2.2-2.2-3.3-.3-.9 0-1.7.3-2.2Z"/>
            @break
    @endswitch
</svg>
