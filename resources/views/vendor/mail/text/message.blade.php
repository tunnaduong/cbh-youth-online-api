<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. Bảo lưu mọi quyền
            Bạn nhận email này vì đã đăng ký nhận bản tin từ CBH Youth Online.
            @if(!empty($unsubscribeUrl))
                Hủy nhận bản tin: {{ $unsubscribeUrl }}
            @endif
            Cài đặt thông báo: {{ rtrim(env('APP_UI_URL', 'http://localhost:3000'), '/') . '/settings' }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
