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
            <p style="margin-top: 16px; font-size: 12px; color: #777;">
                Bạn nhận email này vì đã đăng ký nhận bản tin từ CBH Youth Online.<br>
                @if(!empty($unsubscribeUrl))
                    <a href="{{ $unsubscribeUrl }}" style="color: #3869d4;">Hủy nhận bản tin</a>
                    &nbsp;|&nbsp;
                @endif
                <a href="{{ rtrim(env('APP_UI_URL', 'http://localhost:3000'), '/') . '/settings' }}" style="color: #3869d4;">Cài đặt thông báo</a>
            </p>
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
