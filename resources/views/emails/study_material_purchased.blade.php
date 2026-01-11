<x-mail::message>
# Xin chào!

Tài liệu **{{ $materialTitle }}** của bạn vừa được một thành viên mua.

**Thông tin chi tiết:**
- **Người mua:** {{ $buyerName }}
- **Số điểm nhận được:** +{{ $price }} điểm

Chúc mừng bạn đã có thêm thu nhập từ việc chia sẻ kiến thức! Bạn có thể tiếp tục đăng thêm nhiều tài liệu hữu ích khác để giúp đỡ cộng đồng và nhận thêm điểm thưởng.

<x-mail::button :url="$url">
Xem tài liệu của bạn
</x-mail::button>

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
