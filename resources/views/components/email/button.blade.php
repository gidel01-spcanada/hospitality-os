@props(['href', 'variant' => 'primary'])
@php($emailPrimary = $variant === 'primary')
<table role="presentation" class="email-button" cellspacing="0" cellpadding="0" style="margin:6px 8px 6px 0;display:inline-table;">
    <tr>
        <td align="center" style="border-radius:8px;background:{{ $emailPrimary ? '#0f5b4c' : '#ffffff' }};border:1px solid #0f5b4c;">
            <a href="{{ $href }}" style="display:inline-block;padding:13px 22px;font-size:15px;font-weight:bold;line-height:1.2;color:{{ $emailPrimary ? '#ffffff' : '#0f5b4c' }};text-decoration:none;border-radius:8px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
