@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            {{-- @if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
@else
{{ $slot }}
@endif --}}
            <img src="https://api.chuyenbienhoa.com/images/logo.png" alt="Chuyên Biên Hòa Online Logo" class="logo">
        </a>
    </td>
</tr>
