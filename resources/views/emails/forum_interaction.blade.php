<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# {{ $title }}

{{ $message }}

<x-mail::button :url="$url">
Xem trên diễn đàn
</x-mail::button>

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>