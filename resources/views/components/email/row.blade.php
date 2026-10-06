@props(['label', 'emphasis' => false])
<tr>
    <th scope="row" style="padding:7px 12px 7px 0;text-align:left;vertical-align:top;font-weight:normal;font-size:14px;color:#5b6b70;">{{ $label }}</th>
    <td {{ $attributes }} style="padding:7px 0;text-align:right;vertical-align:top;font-size:{{ $emphasis ? '16px' : '14px' }};font-weight:{{ $emphasis ? 'bold' : 'normal' }};color:#1d2a2d;overflow-wrap:anywhere;">{{ $slot }}</td>
</tr>
