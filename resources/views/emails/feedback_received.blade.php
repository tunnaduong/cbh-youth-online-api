<x-mail::message>
# Có {{ mb_strtolower($typeLabel) }} mới #{{ $feedback->id }}

**Người gửi:** {{ $senderName }}
@if ($contactEmail)
<br>**Email liên hệ:** {{ $contactEmail }}
@endif
<br>**Nền tảng:** {{ $platformLabel }}
@if ($feedback->page_url)
<br>**Trang/nguồn:** {{ $feedback->page_url }}
@endif
@if ($feedback->device_info)
<br>**Thiết bị:** {{ $feedback->device_info }}
@endif
<br>**Thời gian:** {{ $feedback->created_at?->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}

<x-mail::panel>
{!! nl2br(e($feedback->content)) !!}
</x-mail::panel>

@if (!empty($feedback->image_urls))
**Ảnh đính kèm ({{ count($feedback->image_urls) }}):**

@foreach ($feedback->image_urls as $i => $url)
- [Ảnh {{ $i + 1 }}]({{ $url }})
@endforeach
@endif

<x-mail::button :url="$adminUrl">
Xử lý trong trang quản trị
</x-mail::button>

Trả lời email này để phản hồi trực tiếp cho người gửi (nếu họ để lại email).

CYO - Diễn đàn học sinh Chuyên Biên Hòa.
</x-mail::message>
