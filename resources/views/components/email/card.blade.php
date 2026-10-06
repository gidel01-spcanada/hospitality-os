@props(['title' => null, 'tone' => 'default'])
@php
    [$emailCardBg, $emailCardBorder] = [
        'default' => ['#ffffff', '#e3e8e5'],
        'muted' => ['#f7f9f8', '#e3e8e5'],
        'success' => ['#ecf8f1', '#9fd4b5'],
        'warning' => ['#fff8e8', '#efd28d'],
        'info' => ['#eef5fb', '#b7d3ea'],
        'danger' => ['#fdf0ef', '#efb4ae'],
    ][$tone] ?? ['#ffffff', '#e3e8e5'];
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px;background:{{ $emailCardBg }};border:1px solid {{ $emailCardBorder }};border-radius:10px;border-collapse:separate;">
    <tr>
        <td class="email-card" style="padding:18px 20px;overflow-wrap:anywhere;">
            @if ($title)<p style="margin:0 0 12px;font-size:12px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:#5b6b70;">{{ $title }}</p>@endif
            {{ $slot }}
        </td>
    </tr>
</table>
