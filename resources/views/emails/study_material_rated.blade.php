<x-mail::message>
# Xin chào!

Tài liệu **{{ $materialTitle }}** của bạn vừa nhận được một đánh giá mới.

**Chi tiết đánh giá:**
- **Người đánh giá:** {{ $raterName }}
- **Số sao:** {{ $rating }} / 5 ⭐
@if($comment)
- **Nhận xét:** "{{ $comment }}"
@endif

Cảm ơn bạn đã đóng góp tài liệu hữu ích cho cộng đồng. Những phản hồi này sẽ giúp bạn hoàn thiện hơn các tài liệu tiếp theo!

<x-mail::button :url="$url">
Xem chi tiết đánh giá
</x-mail::button>

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
